<?php

namespace App\Policies;

use App\Enums\EnrollmentRole;
use App\Enums\Role;
use App\Models\Course;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Hak akses kuis mengikuti mata kuliahnya. Kuis draf hanya terlihat oleh pengelola. Penilaian esai terkunci
 * saat semester dikunci admin.
 */
class QuizPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Quiz $quiz): bool
    {
        $quiz->loadMissing('course');

        if (! Gate::forUser($user)->allows('view', $quiz->course)) {
            return false;
        }

        return $quiz->is_published || Gate::forUser($user)->allows('update', $quiz->course);
    }

    public function create(User $user, Course $course): bool
    {
        return Gate::forUser($user)->allows('update', $course);
    }

    public function update(User $user, Quiz $quiz): bool
    {
        $quiz->loadMissing('course');

        return Gate::forUser($user)->allows('update', $quiz->course);
    }

    public function delete(User $user, Quiz $quiz): bool
    {
        return $this->update($user, $quiz);
    }

    /**
     * Hasil seluruh peserta: pengelola mata kuliah dan ketua program studi (hanya baca).
     */
    public function viewResults(User $user, Quiz $quiz): bool
    {
        return $this->update($user, $quiz) || $user->hasAppRole(Role::Principal);
    }

    public function grade(User $user, Quiz $quiz): bool
    {
        if (! $this->update($user, $quiz)) {
            return false;
        }

        $quiz->loadMissing('course.term');

        return ! $quiz->course->term->is_locked;
    }

    /**
     * Mengerjakan: hanya mahasiswa yang terdaftar sebagai peserta dan kuisnya terbit. Jendela waktu dan sisa percobaan
     * diperiksa StartAttempt.
     */
    public function attempt(User $user, Quiz $quiz): bool
    {
        return $quiz->is_published
            && $user->hasAppRole(Role::Student)
            && $quiz->course()->first()?->enrollments()
                ->where('user_id', $user->id)->where('role', EnrollmentRole::Student->value)->exists();
    }
}
