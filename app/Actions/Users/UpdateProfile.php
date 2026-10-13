<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Support\StoredFile;
use Illuminate\Http\UploadedFile;

class UpdateProfile
{
    /**
     * Memperbarui profil sendiri. Foto baru menggantikan yang lama (berkas lama dihapus); NIM/NIDN dan peran
     * tidak dapat diubah di sini. Validasi dilakukan pemanggil.
     *
     * @param  array{name: string, email: string|null, phone: string|null, bio: string|null}  $data
     */
    public function handle(User $user, array $data, ?UploadedFile $avatar = null, bool $removeAvatar = false): User
    {
        $attributes = [
            'name' => trim($data['name']),
            'email' => $this->blank($data['email']),
            'phone' => $this->blank($data['phone']),
            'bio' => $this->blank($data['bio']),
        ];

        $old = $user->avatar_path;

        if ($avatar) {
            $stored = StoredFile::store($avatar, "users/{$user->id}/avatar");
            $attributes += ['avatar_path' => $stored['file_path'], 'avatar_mime' => $stored['mime_type']];
        } elseif ($removeAvatar) {
            $attributes += ['avatar_path' => null, 'avatar_mime' => null];
        }

        $user->update($attributes);

        if ($old && ($avatar || $removeAvatar)) {
            StoredFile::delete($old);
        }

        return $user;
    }

    private function blank(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
