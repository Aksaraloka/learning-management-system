<?php

namespace Database\Factories;

use App\Actions\Setup\EnsureRoles;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'identity_number' => fake()->unique()->numerify('##########'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Beri satu atau beberapa peran setelah pengguna dibuat.
     */
    public function role(Role ...$roles): static
    {
        return $this->afterCreating(function (User $user) use ($roles) {
            app(EnsureRoles::class)->handle();

            $user->assignRole(array_map(fn (Role $role) => $role->value, $roles));
        });
    }

    public function unverified(): static
    {
        return $this->state(['email_verified_at' => null]);
    }
}
