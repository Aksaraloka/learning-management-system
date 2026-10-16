<?php

namespace App\Support\Academic;

/**
 * Status mahasiswa di Academic. Hanya `aktif` dan `cuti` yang boleh masuk ke Learn; status lain (`nonaktif`,
 * `mutasi`, `lulus`, `keluar`) ditolak, datanya tetap disimpan.
 */
final class AcademicStatus
{
    /** @var list<string> */
    public const ALLOWED = ['aktif', 'cuti'];

    public static function allowsAccess(?string $status): bool
    {
        return $status !== null && in_array(mb_strtolower($status), self::ALLOWED, true);
    }
}
