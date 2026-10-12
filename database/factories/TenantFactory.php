<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Factories\Factory;
use Spatie\Permission\Models\Role as RoleModel;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'SMP Negeri '.fake()->numberBetween(1, 99).' '.fake()->city(),
            'slug' => fake()->unique()->lexify('sekolah-????'),
            'level' => 'school',
            'plan' => 'free',
            'status' => Tenant::STATUS_ACTIVE,
        ];
    }

    public function campus(): static
    {
        return $this->state(['name' => 'Universitas '.fake()->city(), 'level' => 'campus']);
    }

    public function suspended(): static
    {
        return $this->state(['status' => Tenant::STATUS_SUSPENDED]);
    }

    /**
     * Sekaligus membuat peran bawaan tenant (dibutuhkan sebelum assignRole).
     */
    public function withRoles(): static
    {
        return $this->afterCreating(function (Tenant $tenant) {
            Tenancy::run($tenant, function () {
                foreach (Role::tenantRoles() as $role) {
                    RoleModel::findOrCreate($role->value, 'web');
                }
            });
        });
    }
}
