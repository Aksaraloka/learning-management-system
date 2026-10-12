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
     * Login dengan email atau NIS/NIM/NIP. Pencarian otomatis dibatasi ke tenant
     * aktif (TenantScope); di domain utama hanya akun platform yang boleh masuk.
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

        $user = $this->find($identifier);

        if (! $user || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages(['identifier' => __('auth.failed')]);
        }

        RateLimiter::clear($key);
        Auth::login($user, $remember);

        return $user;
    }

    private function find(string $identifier): ?User
    {
        $query = User::query()->where(
            fn ($q) => $q->where('email', $identifier)->orWhere('identity_number', $identifier)
        );

        if (app()->bound('tenant') === false) {
            $query->whereNull('tenant_id');
        }

        return $query->first();
    }
}
