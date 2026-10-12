<?php

namespace App\Policies;

use App\Enums\EnrollmentRole;
use App\Enums\Role;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Hak akses tugas mengikuti kelasnya. Penilaian tambahan terkunci saat semester dikunci admin.
 */
class AssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Assignment $assignment): bool
    {
        return $this->onCourse($user, 'view', $assignment);
    }

    public function create(User $user, Course $course): bool
    {
        return Gate::forUser($user)->allows('update', $course);
    }

    public function update(User $user, Assignment $assignment): bool
    {
        return $this->onCourse($user, 'update', $assignment);
    }

    public function delete(User $user, Assignment $assignment): bool
    {
        return $this->update($user, $assignment);
    }

    public function copy(User $user, Assignment $assignment): bool
    {
        return $this->update($user, $assignment);
    }

    /**
     * Daftar kiriman semua mahasiswa: pengelola kelas dan kepala (hanya baca).
     */
    public function viewSubmissions(User $user, Assignment $assignment): bool
    {
        return $this->update($user, $assignment) || $user->hasAppRole(Role::Principal);
    }

    /**
     * Memberi/mengubah nilai dan mengembalikan kiriman. dosen tidak bisa mengubah nilai
     * setelah semester dikunci admin.
     */
    public function grade(User $user, Assignment $assignment): bool
    {
        if (! $this->update($user, $assignment)) {
            return false;
        }

        $assignment->loadMissing('course.term');

        return ! $assignment->course->term->is_locked;
    }

    /**
     * Mengumpulkan: hanya mahasiswa yang terdaftar sebagai peserta kelas. Aturan tenggat ada di
     * Assignment::acceptsSubmissionFrom().
     */
    public function submit(User $user, Assignment $assignment): bool
    {
        return $user->hasAppRole(Role::Student)
            && $assignment->course()->first()?->enrollments()
                ->where('user_id', $user->id)->where('role', EnrollmentRole::Student->value)->exists();
    }

    private function onCourse(User $user, string $ability, Assignment $assignment): bool
    {
        $assignment->loadMissing('course');

        return Gate::forUser($user)->allows($ability, $assignment->course);
    }
}
