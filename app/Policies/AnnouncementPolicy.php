<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Pengumuman kelas: dosen kelas itu dan admin. Pengumuman universitas (tanpa kelas): hanya admin.
 */
class AnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user, ?Course $course = null): bool
    {
        if ($course === null) {
            return $user->isAdmin();
        }

        return Gate::forUser($user)->allows('update', $course);
    }

    /**
     * Penulis, admin, atau dosen kelas tempat pengumuman itu dibuat.
     */
    public function update(User $user, Announcement $announcement): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($announcement->course_id === null) {
            return false;
        }

        $announcement->loadMissing('course');

        return $announcement->user_id === $user->id || Gate::forUser($user)->allows('update', $announcement->course);
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $this->update($user, $announcement);
    }
}
