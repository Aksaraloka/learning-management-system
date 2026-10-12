<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null && $user->hasAppRole(Role::Admin, Role::Principal);
    }

    public function view(User $user, User $target): bool
    {
        return $user->is($target) || ($this->viewAny($user) && $user->tenant_id === $target->tenant_id);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null && $user->isAdmin();
    }

    public function import(User $user): bool
    {
        return $this->create($user);
    }

    public function update(User $user, User $target): bool
    {
        return $user->is($target) || ($this->create($user) && $user->tenant_id === $target->tenant_id);
    }

    public function resetPassword(User $user, User $target): bool
    {
        return $this->create($user) && $user->tenant_id === $target->tenant_id && ! $user->is($target);
    }

    public function delete(User $user, User $target): bool
    {
        return $this->resetPassword($user, $target);
    }
}
