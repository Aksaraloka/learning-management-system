<?php

use App\Enums\Role;
use App\Livewire\Auth\Login;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Academic\AcademicSso;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(fn () => enableAcademic());

/**
 * @param  array<string, mixed>  $profile
 */
function fakeAcademicMe(array $profile = [], int $meStatus = 200): void
{
    Http::swap(new Factory);
    Http::fake([
        'academic.test/oauth/token' => Http::response(['access_token' => 'user-token', 'expires_in' => 3600]),
        'academic.test/api/integration/v1/me' => $meStatus === 200
            ? Http::response(['data' => $profile + [
                'id' => 501, 'name' => 'Andi Pratama', 'email' => 'andi@kampus.test', 'identity_number' => '2260001',
                'role' => 'student', 'is_homeroom' => false, 'status' => 'aktif', 'updated_at' => now()->toIso8601String(),
            ]])
            : Http::response(['message' => 'Forbidden'], $meStatus),
    ]);
}

function ssoCallback(string $state = 'state-1', string $code = 'code-1')
{
    return test()->withSession([AcademicSso::SESSION_KEY => ['state' => 'state-1', 'verifier' => 'verifier-1']])
        ->get(route('academic.callback', ['state' => $state, 'code' => $code]));
}

it('redirects to Academic with state and a PKCE S256 challenge', function () {
    $response = $this->get(route('academic.redirect'));

    $response->assertRedirectContains('https://academic.test/oauth/authorize?');

    parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
    $saved = session(AcademicSso::SESSION_KEY);

    expect($query['response_type'])->toBe('code')
        ->and($query['client_id'])->toBe('sso-id')
        ->and($query['scope'])->toBe('profile')
        ->and($query['redirect_uri'])->toBe(route('academic.callback'))
        ->and($query['code_challenge_method'])->toBe('S256')
        ->and($query['state'])->toBe($saved['state'])
        ->and($query['code_challenge'])->toBe(rtrim(strtr(base64_encode(hash('sha256', $saved['verifier'], true)), '+/', '-_'), '='));
});

it('is not available when the integration is off', function () {
    config(['academic.enabled' => false]);

    $this->get(route('academic.redirect'))->assertNotFound();
    $this->get(route('academic.callback'))->assertNotFound();
});

it('creates a new user from Academic and signs in', function () {
    fakeAcademicMe();

    ssoCallback()->assertRedirect(route('home'));

    $user = User::where('academic_user_id', 501)->firstOrFail();

    $this->assertAuthenticatedAs($user);
    expect($user->name)->toBe('Andi Pratama')
        ->and($user->email)->toBe('andi@kampus.test')
        ->and($user->identity_number)->toBe('2260001')
        ->and($user->hasAppRole(Role::Student))->toBeTrue()
        ->and(Hash::check('', $user->password))->toBeFalse();

    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/oauth/token')
        && $r['grant_type'] === 'authorization_code'
        && $r['code'] === 'code-1'
        && $r['code_verifier'] === 'verifier-1'
        && $r['client_id'] === 'sso-id');
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/api/integration/v1/me') && $r->hasHeader('Authorization', 'Bearer user-token'));

    $log = AuditLog::where('action', 'auth.sso_login')->firstOrFail();
    expect($log->user_id)->toBe($user->id)->and(json_encode($log->meta))->not->toContain('user-token');
});

it('links an existing user by email on first login and replaces their roles', function () {
    $existing = makeUser(Role::Teacher, ['email' => 'Andi@Kampus.test', 'identity_number' => null]);

    fakeAcademicMe();

    ssoCallback();

    $existing->refresh();
    $this->assertAuthenticatedAs($existing);
    expect($existing->academic_user_id)->toBe(501)
        ->and(User::count())->toBe(1)
        ->and($existing->hasAppRole(Role::Student))->toBeTrue()
        ->and($existing->hasAppRole(Role::Teacher))->toBeFalse();
});

it('links an existing user by identity number when email does not match', function () {
    $existing = makeUser(Role::Student, ['email' => 'lama@kampus.test', 'identity_number' => '2260001']);

    fakeAcademicMe();

    ssoCallback();

    $this->assertAuthenticatedAs($existing->refresh());
    expect($existing->academic_user_id)->toBe(501)->and($existing->email)->toBe('andi@kampus.test');
});

it('keeps using academic_user_id after the first login', function () {
    $linked = makeUser(Role::Student, ['email' => 'lama@kampus.test', 'identity_number' => null, 'academic_user_id' => 501]);
    makeUser(Role::Student, ['email' => 'andi@kampus.test']);

    fakeAcademicMe(['email' => 'baru@kampus.test']);

    ssoCallback();

    $this->assertAuthenticatedAs($linked->refresh());
    expect($linked->email)->toBe('baru@kampus.test')->and(User::count())->toBe(2);
});

it('rejects a wrong state without signing in', function () {
    fakeAcademicMe();

    ssoCallback(state: 'salah')->assertRedirect(route('login'))->assertSessionHas('sso_error');

    $this->assertGuest();
    Http::assertNothingSent();
});

it('rejects a callback with no saved session state', function () {
    fakeAcademicMe();

    $this->get(route('academic.callback', ['state' => 'state-1', 'code' => 'x']))->assertRedirect(route('login'))->assertSessionHas('sso_error');

    $this->assertGuest();
});

it('shows a message when Academic answers 403 for /me', function () {
    fakeAcademicMe(meStatus: 403);

    ssoCallback()->assertRedirect(route('login'))->assertSessionHas('sso_error', __('academic.sso.forbidden'));

    $this->assertGuest();
    expect(User::count())->toBe(0);
});

it('shows a message when the user denies access at Academic', function () {
    $this->get(route('academic.callback', ['error' => 'access_denied']))->assertRedirect(route('login'))->assertSessionHas('sso_error');

    $this->assertGuest();
});

it('rejects students whose status is not allowed', function (string $status) {
    fakeAcademicMe(['status' => $status]);

    ssoCallback()->assertRedirect(route('login'))->assertSessionHas('sso_error');

    $this->assertGuest();
    expect(User::count())->toBe(0);
})->with(['nonaktif', 'mutasi', 'lulus', 'keluar']);

it('lets students on leave sign in', function () {
    fakeAcademicMe(['status' => 'cuti']);

    ssoCallback();

    $this->assertAuthenticated();
});

it('adds and revokes the homeroom role from is_homeroom on every login', function () {
    fakeAcademicMe(['role' => 'teacher', 'is_homeroom' => true, 'status' => null]);
    ssoCallback();

    $user = User::where('academic_user_id', 501)->firstOrFail();
    expect($user->hasAppRole(Role::Teacher))->toBeTrue()->and($user->hasAppRole(Role::Homeroom))->toBeTrue();

    auth()->logout();
    fakeAcademicMe(['role' => 'teacher', 'is_homeroom' => false, 'status' => null]);
    ssoCallback();

    $user = $user->fresh();
    expect($user->hasAppRole(Role::Teacher))->toBeTrue()->and($user->hasAppRole(Role::Homeroom))->toBeFalse();
});

it('rejects an unknown role', function () {
    fakeAcademicMe(['role' => 'alien']);

    ssoCallback()->assertRedirect(route('login'))->assertSessionHas('sso_error');

    $this->assertGuest();
});

it('shows the campus button and hides the password form behind the admin link', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee(__('ui.auth.sso_button'))
        ->assertSee(route('academic.redirect'), false)
        ->assertSee(__('ui.auth.admin_login'));
});

it('does not show the campus button when the integration is off', function () {
    config(['academic.enabled' => false]);

    $this->get(route('login'))->assertOk()->assertDontSee(__('ui.auth.sso_button'))->assertSee(__('ui.auth.forgot'));
});

it('still lets an admin sign in with a password', function () {
    makeUser(Role::Admin, ['email' => 'admin@kampus.test', 'password' => 'rahasia123']);

    Livewire::test(Login::class)->set('identifier', 'admin@kampus.test')->set('password', 'rahasia123')->call('login')->assertHasNoErrors();

    $this->assertAuthenticated();
});

it('rejects non-admin users at the password form when the integration is on', function () {
    makeUser(Role::Student, ['email' => 'mhs@kampus.test', 'password' => 'rahasia123']);

    Livewire::test(Login::class)->set('identifier', 'mhs@kampus.test')->set('password', 'rahasia123')->call('login')
        ->assertHasErrors('identifier');

    $this->assertGuest();
});

it('keeps password login for everyone when the integration is off', function () {
    config(['academic.enabled' => false]);
    makeUser(Role::Student, ['email' => 'mhs@kampus.test', 'password' => 'rahasia123']);

    Livewire::test(Login::class)->set('identifier', 'mhs@kampus.test')->set('password', 'rahasia123')->call('login')->assertHasNoErrors();

    $this->assertAuthenticated();
});
