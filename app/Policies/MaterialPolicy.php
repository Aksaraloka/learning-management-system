<?php

namespace App\Policies;

use App\Models\Material;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Hak akses materi mengikuti kelasnya.
 */
class MaterialPolicy
{
    public function view(User $user, Material $material): bool
    {
        return $this->allows($user, 'view', $material);
    }

    public function create(User $user, Section $section): bool
    {
        return Gate::forUser($user)->allows('update', $section);
    }

    public function update(User $user, Material $material): bool
    {
        return $this->allows($user, 'update', $material);
    }

    public function delete(User $user, Material $material): bool
    {
        return $this->update($user, $material);
    }

    private function allows(User $user, string $ability, Material $material): bool
    {
        $material->loadMissing('section.course');

        return Gate::forUser($user)->allows($ability, $material->section->course);
    }
}
