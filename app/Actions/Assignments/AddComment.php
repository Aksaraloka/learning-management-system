<?php

namespace App\Actions\Assignments;

use App\Models\Assignment;
use App\Models\AssignmentComment;
use App\Models\User;

class AddComment
{
    /**
     * Komentar publik (terlihat semua anggota kelas) atau privat (percakapan satu mahasiswa dengan dosen).
     * Untuk komentar privat, $threadStudent adalah mahasiswa pemilik percakapan: mahasiswa sendiri, atau mahasiswa yang
     * dibalas dosen.
     */
    public function handle(Assignment $assignment, User $author, string $body, bool $private = false, ?User $threadStudent = null): AssignmentComment
    {
        return $assignment->comments()->create([
            'user_id' => $author->id,
            'body' => trim($body),
            'is_private' => $private,
            'thread_user_id' => $private ? ($threadStudent ?? $author)->id : null,
        ]);
    }
}
