<?php

namespace App\Policies;

use App\Models\AcademicTerm;
use App\Models\User;

class AcademicTermPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AcademicTerm $term): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, AcademicTerm $term): bool
    {
        return $this->create($user) && ! $term->is_locked && ! $term->isManagedByAcademic();
    }

    public function lock(User $user, AcademicTerm $term): bool
    {
        return $this->create($user) && ! $term->isManagedByAcademic();
    }

    public function delete(User $user, AcademicTerm $term): bool
    {
        return $this->update($user, $term);
    }
}
