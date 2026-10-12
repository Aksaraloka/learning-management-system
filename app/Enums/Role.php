<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Principal = 'principal';
    case Teacher = 'teacher';
    case Homeroom = 'homeroom';
    case Student = 'student';
    case Parent = 'parent';

    /**
     * Peran yang ada di dalam tenant (semua kecuali super admin platform).
     *
     * @return list<self>
     */
    public static function tenantRoles(): array
    {
        return array_values(array_filter(self::cases(), fn (self $role) => $role !== self::SuperAdmin));
    }

    /**
     * Peran yang boleh diberikan lewat impor/pembuatan massal oleh admin.
     *
     * @return list<self>
     */
    public static function importable(): array
    {
        return [self::Student, self::Teacher, self::Homeroom, self::Principal];
    }

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
            self::Principal => term('principal'),
            self::Teacher => term('teacher'),
            self::Homeroom => term('homeroom'),
            self::Student => term('student'),
            self::Parent => 'Orang Tua',
        };
    }

    /**
     * Terima kode Inggris maupun istilah Indonesia dari file impor.
     */
    public static function fromInput(string $input): ?self
    {
        $key = mb_strtolower(trim($input));

        $aliases = [
            'guru' => self::Teacher, 'dosen' => self::Teacher,
            'siswa' => self::Student, 'mahasiswa' => self::Student,
            'wali kelas' => self::Homeroom, 'dosen wali' => self::Homeroom,
            'kepala sekolah' => self::Principal, 'kaprodi' => self::Principal,
        ];

        return $aliases[$key] ?? self::tryFrom($key);
    }
}
