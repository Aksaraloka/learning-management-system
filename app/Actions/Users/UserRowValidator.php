<?php

namespace App\Actions\Users;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

/**
 * Memvalidasi satu baris impor pengguna terhadap aturan, isi file sebelumnya,
 * dan data yang sudah ada di tenant aktif. Satu instance per impor/pratinjau.
 */
class UserRowValidator
{
    /** @var array<string, bool>|null */
    private ?array $identities = null;

    /** @var array<string, bool>|null */
    private ?array $emails = null;

    /**
     * @param  array<string, mixed>  $row
     * @return array{errors: list<string>, data: array<string, mixed>|null}
     */
    public function validate(array $row): array
    {
        // Excel membaca NIS/NIM sebagai angka; samakan semuanya menjadi teks.
        $row = array_map(fn ($v) => is_scalar($v) ? trim((string) $v) : $v, $row);

        $validator = Validator::make($row, [
            'name' => ['required', 'string', 'max:255'],
            'identity_number' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'role' => ['required', 'string'],
            'password' => ['nullable', 'string', 'min:8', 'max:100'],
        ], [], [
            'name' => __('validation.attributes.name'),
            'identity_number' => __('validation.attributes.identity_number'),
            'role' => 'peran',
        ]);

        $errors = $validator->errors()->all();
        $roles = [];

        if (! $validator->errors()->has('role')) {
            foreach (preg_split('/[|,;]/', (string) $row['role']) ?: [] as $part) {
                $role = Role::fromInput($part);

                if (! $role || ! in_array($role, Role::importable(), true)) {
                    $errors[] = "Peran \"{$part}\" tidak dikenal atau tidak boleh diimpor.";
                } else {
                    $roles[$role->value] = $role;
                }
            }
        }

        $identity = (string) ($row['identity_number'] ?? '');
        $email = mb_strtolower((string) ($row['email'] ?? ''));

        if ($identity !== '' && isset($this->identities()[$identity])) {
            $errors[] = "NIS/NIM/NIP {$identity} sudah terdaftar.";
        }

        if ($email !== '' && isset($this->emails()[$email])) {
            $errors[] = "Email {$email} sudah terdaftar.";
        }

        if ($errors !== []) {
            return ['errors' => array_values(array_unique($errors)), 'data' => null];
        }

        $this->identities[$identity] = true;

        if ($email !== '') {
            $this->emails[$email] = true;
        }

        return ['errors' => [], 'data' => [
            'name' => $row['name'],
            'identity_number' => $identity,
            'email' => $email !== '' ? $email : null,
            'password' => ($row['password'] ?? '') !== '' ? $row['password'] : $identity,
            'roles' => array_keys($roles),
        ]];
    }

    /**
     * @return array<string, bool>
     */
    private function identities(): array
    {
        return $this->identities ??= User::withTrashed()->whereNotNull('identity_number')
            ->pluck('identity_number')->mapWithKeys(fn ($v) => [(string) $v => true])->all();
    }

    /**
     * Email unik secara global, jadi pemeriksaan melewati TenantScope.
     *
     * @return array<string, bool>
     */
    private function emails(): array
    {
        return $this->emails ??= User::withoutGlobalScopes()->withTrashed()->whereNotNull('email')
            ->pluck('email')->mapWithKeys(fn ($v) => [mb_strtolower($v) => true])->all();
    }
}
