<?php

namespace App\Policies;

use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class QuizAttemptPolicy
{
    /**
     * Pemilik, pengelola mata kuliah, dan ketua program studi (hanya baca). Mahasiswa lain tidak pernah.
     */
    public function view(User $user, QuizAttempt $attempt): bool
    {
        if ($attempt->user_id === $user->id) {
            return true;
        }

        $attempt->loadMissing('quiz');

        return Gate::forUser($user)->allows('viewResults', $attempt->quiz);
    }

    /** Menjawab / menyelesaikan: hanya pemilik. */
    public function answer(User $user, QuizAttempt $attempt): bool
    {
        return $attempt->user_id === $user->id;
    }

    public function grade(User $user, QuizAttempt $attempt): bool
    {
        $attempt->loadMissing('quiz');

        return Gate::forUser($user)->allows('grade', $attempt->quiz);
    }
}
