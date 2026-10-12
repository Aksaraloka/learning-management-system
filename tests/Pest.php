<?php

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => Tenancy::forget())
    ->in('Feature');

/**
 * Tenant dengan peran bawaan.
 *
 * @param  array<string, mixed>  $attributes
 */
function makeTenant(array $attributes = []): Tenant
{
    return Tenant::factory()->withRoles()->create($attributes);
}

/**
 * Pengguna dalam sebuah tenant dengan peran tertentu.
 *
 * @param  array<string, mixed>  $attributes
 */
function makeUser(Tenant $tenant, Role|array $roles = Role::Student, array $attributes = []): User
{
    return User::factory()->forTenant($tenant)->role(...(is_array($roles) ? $roles : [$roles]))->create($attributes);
}

/**
 * Jalankan request/komponen sebagai pengguna di konteks tenantnya.
 */
function actingInTenant(Tenant $tenant, User $user): void
{
    Tenancy::bind($tenant);
    test()->actingAs($user);
}
