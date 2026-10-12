<?php

namespace App\Actions\Tenants;

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as RoleModel;

class CreateTenant
{
    /**
     * Membuat tenant beserta peran bawaannya dan satu akun admin institusi.
     *
     * @param  array{name: string, slug: string, level: string, plan?: string}  $data
     * @param  array{name: string, email: string, identity_number?: string|null, password?: string|null}  $admin
     * @return array{tenant: Tenant, admin: User, password: string}
     */
    public function handle(array $data, array $admin): array
    {
        return DB::transaction(function () use ($data, $admin) {
            $tenant = Tenant::create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'level' => $data['level'],
                'plan' => $data['plan'] ?? 'free',
                'status' => Tenant::STATUS_ACTIVE,
            ]);

            $password = $admin['password'] ?? Str::password(12, symbols: false);

            $user = Tenancy::run($tenant, function () use ($tenant, $admin, $password) {
                foreach (Role::tenantRoles() as $role) {
                    RoleModel::findOrCreate($role->value, 'web');
                }

                $user = User::create([
                    'tenant_id' => $tenant->id,
                    'name' => $admin['name'],
                    'email' => $admin['email'],
                    'identity_number' => $admin['identity_number'] ?? null,
                    'password' => $password,
                ]);
                $user->assignRole(Role::Admin->value);

                return $user;
            });

            return ['tenant' => $tenant, 'admin' => $user, 'password' => $password];
        });
    }
}
