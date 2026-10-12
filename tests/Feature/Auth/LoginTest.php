<?php

use App\Enums\Role;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\ResetPassword;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

beforeEach(function () {
    $this->student = makeUser(Role::Student, [
        'email' => 'andi@contoh.test', 'identity_number' => '2026001', 'password' => 'rahasia123',
    ]);
});

function attemptLogin(string $identifier, string $password)
{
    return Livewire::test(Login::class)->set('identifier', $identifier)->set('password', $password)->call('login');
}

it('shows the login page with the institution name', function () {
    $this->get('/login')->assertOk()->assertSee('SMP Contoh')->assertSee('Masuk');
});

it('sends guests from the root to login', function () {
    $this->get('/')->assertRedirect(route('login'));
});

it('logs in with email', function () {
    attemptLogin('andi@contoh.test', 'rahasia123')->assertHasNoErrors()->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($this->student);
});

it('logs in with NIS/NIM', function () {
    attemptLogin('2026001', 'rahasia123')->assertHasNoErrors();

    $this->assertAuthenticatedAs($this->student);
});

it('rejects a wrong password or unknown account', function () {
    attemptLogin('2026001', 'salah')->assertHasErrors('identifier');
    attemptLogin('tidak-ada@contoh.test', 'rahasia123')->assertHasErrors('identifier');

    $this->assertGuest();
});

it('rate limits repeated failed logins', function () {
    foreach (range(1, 5) as $_) {
        attemptLogin('2026001', 'salah');
    }

    attemptLogin('2026001', 'rahasia123')->assertHasErrors('identifier');

    $this->assertGuest();
});

it('requires both fields', function () {
    Livewire::test(Login::class)->call('login')->assertHasErrors(['identifier', 'password']);
});

it('redirects guests away from protected pages and logs out', function () {
    $this->get('/profil')->assertRedirect(route('login'));
    $this->get('/admin/pengguna')->assertRedirect(route('login'));

    $this->actingAs($this->student)->post('/logout')->assertRedirect(route('home'));

    $this->assertGuest();
});

it('sends a reset link only to existing emails without revealing which', function () {
    Notification::fake();

    Livewire::test(ForgotPassword::class)->set('email', 'tidak-ada@contoh.test')->call('send')->assertSet('sent', true);
    Notification::assertNothingSent();

    Livewire::test(ForgotPassword::class)->set('email', 'andi@contoh.test')->call('send')->assertSet('sent', true);
    Notification::assertSentTo($this->student, ResetPasswordNotification::class);
});

it('resets the password with a valid token', function () {
    $token = Password::createToken($this->student);

    Livewire::withQueryParams(['email' => 'andi@contoh.test'])
        ->test(ResetPassword::class, ['token' => $token])
        ->set('password', 'baru12345')->set('password_confirmation', 'baru12345')
        ->call('reset_password')->assertHasNoErrors()->assertRedirect(route('login'));

    expect(Hash::check('baru12345', $this->student->fresh()->password))->toBeTrue();
});

it('rejects an invalid reset token', function () {
    Livewire::withQueryParams(['email' => 'andi@contoh.test'])
        ->test(ResetPassword::class, ['token' => 'salah'])
        ->set('password', 'baru12345')->set('password_confirmation', 'baru12345')
        ->call('reset_password')->assertHasErrors('email');
});

it('stores the chosen theme on the account', function () {
    $this->actingAs($this->student)->putJson('/theme', ['theme' => 'dark'])->assertOk();

    expect($this->student->fresh()->theme)->toBe('dark');

    $this->actingAs($this->student)->putJson('/theme', ['theme' => 'neon'])->assertUnprocessable();
});

it('applies the account theme on a new device', function () {
    $this->student->forceFill(['theme' => 'dark'])->save();

    $this->actingAs($this->student)->get('/profil')->assertSee("var server = 'dark'", false);
});
