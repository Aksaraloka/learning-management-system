<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenancy::id(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'identity_number' => fake()->unique()->numerify('##########'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(['tenant_id' => $tenant->id]);
    }

    /**
     * Beri peran setelah dibuat. Tenant pengguna harus punya peran bawaan (TenantFactory::withRoles).
     */
    public function role(Role ...$roles): static
    {
        return $this->afterCreating(function (User $user) use ($roles) {
            $assign = fn () => $user->assignRole(array_map(fn (Role $r) => $r->value, $roles));

            $user->tenant_id
                ? Tenancy::run(Tenant::findOrFail($user->tenant_id), $assign)
                : $assign();
        });
    }

    public function superAdmin(): static
    {
        return $this->state(['tenant_id' => null, 'identity_number' => null])
            ->afterCreating(function (User $user) {
                $team = config('tenancy.platform_team_id');

                app(PermissionRegistrar::class)->setPermissionsTeamId($team);

                \Spatie\Permission\Models\Role::findOrCreate(Role::SuperAdmin->value, 'web')
                    ->forceFill(['tenant_id' => $team])->save();

                $user->assignRole(Role::SuperAdmin->value);
            });
    }

    public function unverified(): static
    {
        return $this->state(['email_verified_at' => null]);
    }
}
