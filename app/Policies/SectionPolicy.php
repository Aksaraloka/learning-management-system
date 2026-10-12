<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Hak akses pertemuan mengikuti kelasnya.
 */
class SectionPolicy
{
    public function view(User $user, Section $section): bool
    {
        return $this->allows($user, 'view', $section);
    }

    public function create(User $user, Course $course): bool
    {
        return Gate::forUser($user)->allows('update', $course);
    }

    public function update(User $user, Section $section): bool
    {
        return $this->allows($user, 'update', $section);
    }

    public function delete(User $user, Section $section): bool
    {
        return $this->update($user, $section);
    }

    private function allows(User $user, string $ability, Section $section): bool
    {
        $section->loadMissing('course');

        return Gate::forUser($user)->allows($ability, $section->course);
    }
}
