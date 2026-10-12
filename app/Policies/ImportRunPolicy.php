<?php

namespace App\Policies;

use App\Models\ImportRun;
use App\Models\User;

class ImportRunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, ImportRun $run): bool
    {
        return $this->viewAny($user);
    }
}
