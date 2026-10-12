<?php

namespace App\Policies;

use App\Models\Assignment;
use App\Models\AssignmentComment;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class AssignmentCommentPolicy
{
    /**
     * Semua anggota kelas boleh berkomentar. Komentar privat hanya antara seorang siswa dan pengajar.
     */
    public function create(User $user, Assignment $assignment): bool
    {
        return Gate::forUser($user)->allows('view', $assignment);
    }

    public function view(User $user, AssignmentComment $comment): bool
    {
        $comment->loadMissing('assignment');

        if (! Gate::forUser($user)->allows('view', $comment->assignment)) {
            return false;
        }

        if (! $comment->is_private) {
            return true;
        }

        return $comment->user_id === $user->id
            || $comment->thread_user_id === $user->id
            || Gate::forUser($user)->allows('update', $comment->assignment);
    }

    public function delete(User $user, AssignmentComment $comment): bool
    {
        $comment->loadMissing('assignment');

        return $comment->user_id === $user->id || Gate::forUser($user)->allows('update', $comment->assignment);
    }
}
