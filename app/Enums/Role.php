<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Principal = 'principal';
    case Teacher = 'teacher';
    case Homeroom = 'homeroom';
    case Student = 'student';
    case Parent = 'parent';

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
            'dosen' => self::Teacher, 'guru' => self::Teacher,
            'mahasiswa' => self::Student, 'siswa' => self::Student,
            'dosen wali' => self::Homeroom, 'wali kelas' => self::Homeroom,
            'kaprodi' => self::Principal, 'ketua program studi' => self::Principal, 'kepala sekolah' => self::Principal,
        ];

        return $aliases[$key] ?? self::tryFrom($key);
    }
}
