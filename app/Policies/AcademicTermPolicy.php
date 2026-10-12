<?php

namespace App\Policies;

use App\Models\AcademicTerm;
use App\Models\User;

class AcademicTermPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, AcademicTerm $term): bool
    {
        return $user->tenant_id === $term->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null && $user->isAdmin();
    }

    public function update(User $user, AcademicTerm $term): bool
    {
        return $this->create($user) && $user->tenant_id === $term->tenant_id && ! $term->is_locked;
    }

    public function lock(User $user, AcademicTerm $term): bool
    {
        return $this->create($user) && $user->tenant_id === $term->tenant_id;
    }

    public function delete(User $user, AcademicTerm $term): bool
    {
        return $this->update($user, $term);
    }
}
