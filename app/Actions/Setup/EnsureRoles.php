<?php

namespace App\Actions\Setup;

use App\Enums\Role;
use Spatie\Permission\Models\Role as RoleModel;

class EnsureRoles
{
    /**
     * Pastikan semua peran aplikasi ada di database (aman dipanggil berulang).
     */
    public function handle(): void
    {
        foreach (Role::cases() as $role) {
            RoleModel::findOrCreate($role->value, 'web');
        }
    }
}
