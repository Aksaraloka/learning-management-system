<?php

namespace App\Policies;

use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class SubmissionPolicy
{
    /**
     * Pemilik, pengelola kelas, dan kepala (hanya baca). mahasiswa lain tidak pernah.
     */
    public function view(User $user, Submission $submission): bool
    {
        if ($submission->user_id === $user->id) {
            return true;
        }

        $submission->loadMissing('assignment');

        return Gate::forUser($user)->allows('viewSubmissions', $submission->assignment);
    }

    public function grade(User $user, Submission $submission): bool
    {
        $submission->loadMissing('assignment');

        return Gate::forUser($user)->allows('grade', $submission->assignment);
    }
}
