<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'identity_number', 'password', 'theme', 'locale', 'phone', 'bio', 'avatar_path', 'avatar_mime'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

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

    public function isAdmin(): bool
    {
        return $this->hasAppRole(Role::Admin);
    }

    public function hasAvatar(): bool
    {
        return $this->avatar_path !== null && $this->avatar_path !== '';
    }

    /** URL foto profil (privat, lewat controller) atau null bila belum ada; `v` memecah cache saat foto diganti. */
    public function avatarUrl(): ?string
    {
        return $this->hasAvatar() ? route('users.avatar', $this).'?v='.($this->updated_at->timestamp ?? 0) : null;
    }
}
