<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Str;

class ResetUserPassword
{
    /**
     * Admin mengatur ulang kata sandi pengguna. Mengembalikan kata sandi baru
     * (acak bila tidak diberikan) untuk ditampilkan sekali kepada admin.
     */
    public function handle(User $user, ?string $password = null): string
    {
        $password ??= Str::password(10, symbols: false);

        $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();

        return $password;
    }
}
