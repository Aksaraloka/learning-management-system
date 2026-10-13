<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

/**
 * Jejak audit: admin dan ketua program studi (hanya baca). Tidak ada hak ubah/hapus.
 */
class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAppRole(Role::Admin, Role::Principal);
    }
}
