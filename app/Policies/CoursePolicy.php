<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Course $course): bool
    {
        return $user->hasAppRole(Role::Admin, Role::Principal) || $course->hasMember($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAppRole(Role::Admin, Role::Teacher);
    }

    /**
     * Mengubah kelas, topik/pertemuan, materi, dan anggota: admin atau pengajar kelas itu.
     */
    public function update(User $user, Course $course): bool
    {
        return $user->isAdmin() || $course->isTaughtBy($user);
    }

    public function delete(User $user, Course $course): bool
    {
        return $this->update($user, $course);
    }

    public function manageMembers(User $user, Course $course): bool
    {
        return $this->update($user, $course);
    }

    /**
     * Daftar peserta lengkap: pengelola kelas dan kepala. Siswa hanya melihat pengajar.
     */
    public function viewRoster(User $user, Course $course): bool
    {
        return $this->update($user, $course) || $user->hasAppRole(Role::Principal);
    }

    /**
     * Bergabung lewat kode: hanya akun siswa yang belum terdaftar di kelas itu.
     */
    public function join(User $user): bool
    {
        return $user->hasAppRole(Role::Student);
    }
}
