<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthenticateUser
{
    public const MAX_ATTEMPTS = 5;

    /**
     * Login dengan email atau NIM/NIDN.
     *
     * @throws ValidationException
     */
    public function handle(string $identifier, string $password, bool $remember, string $ip): User
    {
        $key = Str::lower($identifier).'|'.$ip;

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            event(new Lockout(request()));

            throw ValidationException::withMessages([
                'identifier' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        $user = User::query()
            ->where(fn ($q) => $q->where('email', $identifier)->orWhere('identity_number', $identifier))
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages(['identifier' => __('auth.failed')]);
        }

        RateLimiter::clear($key);
        Auth::login($user, $remember);

        return $user;
    }
}
