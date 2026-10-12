<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Pengguna dengan peran tertentu.
 *
 * @param  array<string, mixed>  $attributes
 */
function makeUser(Role|array $roles = Role::Student, array $attributes = []): User
{
    return User::factory()->role(...(is_array($roles) ? $roles : [$roles]))->create($attributes);
}
