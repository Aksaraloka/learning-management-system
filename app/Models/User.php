<?php

namespace App\Models;

use App\Enums\Role;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['tenant_id', 'name', 'email', 'identity_number', 'password', 'theme', 'locale'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use BelongsToTenant, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function hasAppRole(Role ...$roles): bool
    {
        return $this->hasAnyRole(array_map(fn (Role $role) => $role->value, $roles));
    }

    public function isSuperAdmin(): bool
    {
        return $this->tenant_id === null && $this->hasAppRole(Role::SuperAdmin);
    }

    public function isAdmin(): bool
    {
        return $this->hasAppRole(Role::Admin);
    }
}
