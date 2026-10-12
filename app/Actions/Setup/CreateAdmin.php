<?php

namespace App\Actions\Setup;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Str;

class CreateAdmin
{
    /**
     * Membuat akun admin institusi. Mengembalikan akun dan kata sandi (acak bila tidak diberikan).
     *
     * @return array{user: User, password: string}
     */
    public function handle(string $name, string $email, ?string $password = null, ?string $identityNumber = null): array
    {
        app(EnsureRoles::class)->handle();

        $password ??= Str::password(12, symbols: false);

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'identity_number' => $identityNumber,
            'password' => $password,
        ]);
        $user->assignRole(Role::Admin->value);

        return ['user' => $user, 'password' => $password];
    }
}
