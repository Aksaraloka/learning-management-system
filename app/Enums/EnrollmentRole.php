<?php

namespace App\Enums;

enum EnrollmentRole: string
{
    case Teacher = 'teacher';
    case Student = 'student';

    /**
     * Peran aplikasi yang dibutuhkan agar akun boleh didaftarkan dengan peran kelas ini.
     */
    public function requiredRole(): Role
    {
        return match ($this) {
            self::Teacher => Role::Teacher,
            self::Student => Role::Student,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Teacher => term('teacher'),
            self::Student => term('student'),
        };
    }
}
