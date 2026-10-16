<?php

namespace App\Actions\Academic;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Cocokkan atau buat pengguna Learn dari data pengguna Academic.
 *
 * Urutan pencocokan: `academic_user_id`; bila belum tertaut, email lalu `identity_number` (hanya akun yang belum
 * tertaut ke pengguna Academic lain). Pengguna baru dibuat tanpa kata sandi yang bisa dipakai. Academic adalah
 * pemilik data induk, jadi nama selalu mengikuti Academic; email dan NIM/NIDN ikut selama tidak bentrok dengan
 * akun Learn lain.
 */
class UpsertAcademicUser
{
    /**
     * @param  array<string, mixed>  $data  `id`/`user_id`, `name`, `email`, `identity_number`
     */
    public function handle(array $data): User
    {
        $academicId = (int) ($data['user_id'] ?? $data['id']);
        $email = $this->clean($data['email'] ?? null);
        $identity = $this->clean($data['identity_number'] ?? null);

        $user = User::withTrashed()->where('academic_user_id', $academicId)->first()
            ?? ($email !== null ? User::withTrashed()->whereNull('academic_user_id')->whereRaw('lower(email) = ?', [Str::lower($email)])->first() : null)
            ?? ($identity !== null ? User::withTrashed()->whereNull('academic_user_id')->where('identity_number', $identity)->first() : null);

        if (! $user) {
            $user = new User([
                'name' => (string) ($data['name'] ?? $email ?? $identity ?? 'Pengguna'),
                'email' => $email !== null && ! $this->emailTaken($email) ? $email : null,
                'identity_number' => $identity !== null && ! $this->identityTaken($identity) ? $identity : null,
                'password' => Str::random(64),
            ]);
            $user->academic_user_id = $academicId;
            $user->email_verified_at = now();
            $user->save();

            return $user;
        }

        $user->academic_user_id = $academicId;

        if (! empty($data['name'])) {
            $user->name = (string) $data['name'];
        }

        if ($email !== null && ! $this->emailTaken($email, $user)) {
            $user->email = $email;
        }

        if ($identity !== null && ! $this->identityTaken($identity, $user)) {
            $user->identity_number = $identity;
        }

        $user->save();

        return $user;
    }

    private function clean(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value === '' ? null : $value;
    }

    private function emailTaken(string $email, ?User $except = null): bool
    {
        return User::withTrashed()->whereRaw('lower(email) = ?', [Str::lower($email)])
            ->when($except, fn ($q) => $q->where('id', '!=', $except->id))->exists();
    }

    private function identityTaken(string $identity, ?User $except = null): bool
    {
        return User::withTrashed()->where('identity_number', $identity)
            ->when($except, fn ($q) => $q->where('id', '!=', $except->id))->exists();
    }
}
