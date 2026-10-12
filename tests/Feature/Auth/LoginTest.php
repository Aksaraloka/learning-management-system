<?php

use App\Enums\Role;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\ResetPassword;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = makeTenant(['subdomain' => 'smp1']);
    $this->student = makeUser($this->tenant, Role::Student, [
        'email' => 'andi@contoh.test', 'identity_number' => '2026001', 'password' => 'rahasia123',
    ]);
});

function attemptLogin(string $identifier, string $password)
{
    return Livewire::test(Login::class)->set('identifier', $identifier)->set('password', $password)->call('login');
}

it('logs in with email', function () {
    Tenancy::bind($this->tenant);

    attemptLogin('andi@contoh.test', 'rahasia123')->assertHasNoErrors()->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($this->student);
});

it('logs in with NIS/NIM', function () {
    Tenancy::bind($this->tenant);

    attemptLogin('2026001', 'rahasia123')->assertHasNoErrors();

    $this->assertAuthenticatedAs($this->student);
});

it('rejects a wrong password', function () {
    Tenancy::bind($this->tenant);

    attemptLogin('2026001', 'salah')->assertHasErrors('identifier');

    $this->assertGuest();
});

it('does not log a user into another tenant', function () {
    $other = makeTenant(['subdomain' => 'smp2']);
    Tenancy::bind($other);

    attemptLogin('2026001', 'rahasia123')->assertHasErrors('identifier');
    attemptLogin('andi@contoh.test', 'rahasia123')->assertHasErrors('identifier');

    $this->assertGuest();
});

it('keeps tenant users out of the root domain', function () {
    Tenancy::forget();

    attemptLogin('andi@contoh.test', 'rahasia123')->assertHasErrors('identifier');

    $this->assertGuest();
});

it('lets a super admin log in only on the root domain', function () {
    User::factory()->superAdmin()->create(['email' => 'super@aksaraloka.test', 'password' => 'rahasia123']);

    Tenancy::bind($this->tenant);
    attemptLogin('super@aksaraloka.test', 'rahasia123')->assertHasErrors('identifier');

    Tenancy::forget();
    attemptLogin('super@aksaraloka.test', 'rahasia123')->assertHasNoErrors();

    $this->assertAuthenticated();
});

it('rate limits repeated failed logins', function () {
    Tenancy::bind($this->tenant);

    foreach (range(1, 5) as $_) {
        attemptLogin('2026001', 'salah');
    }

    attemptLogin('2026001', 'rahasia123')->assertHasErrors('identifier');

    $this->assertGuest();
});

it('requires both fields', function () {
    Tenancy::bind($this->tenant);

    Livewire::test(Login::class)->call('login')->assertHasErrors(['identifier', 'password']);
});

it('redirects guests away from protected pages and logs out', function () {
    $this->get($this->tenant->url('/profil'))->assertRedirect(route('login'));

    $this->actingAs($this->student)->post($this->tenant->url('/logout'))->assertRedirect(route('home'));

    $this->assertGuest();
});

it('sends a reset link only to existing emails without revealing which', function () {
    Notification::fake();
    Tenancy::bind($this->tenant);

    Livewire::test(ForgotPassword::class)->set('email', 'tidak-ada@contoh.test')->call('send')->assertSet('sent', true);
    Notification::assertNothingSent();

    Livewire::test(ForgotPassword::class)->set('email', 'andi@contoh.test')->call('send')->assertSet('sent', true);
    Notification::assertSentTo($this->student, ResetPasswordNotification::class);
});

it('resets the password with a valid token', function () {
    Tenancy::bind($this->tenant);
    $token = Password::createToken($this->student);

    Livewire::withQueryParams(['email' => 'andi@contoh.test'])
        ->test(ResetPassword::class, ['token' => $token])
        ->set('password', 'baru12345')->set('password_confirmation', 'baru12345')
        ->call('reset_password')->assertHasNoErrors()->assertRedirect(route('login'));

    expect(Hash::check('baru12345', $this->student->fresh()->password))->toBeTrue();
});

it('rejects an invalid reset token', function () {
    Tenancy::bind($this->tenant);

    Livewire::withQueryParams(['email' => 'andi@contoh.test'])
        ->test(ResetPassword::class, ['token' => 'salah'])
        ->set('password', 'baru12345')->set('password_confirmation', 'baru12345')
        ->call('reset_password')->assertHasErrors('email');
});

it('stores the chosen theme on the account', function () {
    $this->actingAs($this->student)
        ->putJson($this->tenant->url('/theme'), ['theme' => 'dark'])->assertOk();

    expect($this->student->fresh()->theme)->toBe('dark');

    $this->actingAs($this->student)->putJson($this->tenant->url('/theme'), ['theme' => 'neon'])->assertUnprocessable();
});

it('applies the account theme on a new device', function () {
    $this->student->forceFill(['theme' => 'dark'])->save();

    $this->actingAs($this->student)->get($this->tenant->url('/profil'))->assertSee("var server = 'dark'", false);
});
