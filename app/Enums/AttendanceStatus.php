<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Excused = 'excused';
    case Sick = 'sick';
    case Absent = 'absent';

    public function label(): string
    {
        return __('attendance.status.'.$this->value);
    }

    /** Huruf singkat untuk tombol dan ekspor. */
    public function short(): string
    {
        return __('attendance.short.'.$this->value);
    }

    /** Hanya hadir yang dihitung sebagai kehadiran. */
    public function counts(): bool
    {
        return $this === self::Present;
    }

    public function variant(): string
    {
        return match ($this) {
            self::Present => 'success',
            self::Excused => 'info',
            self::Sick => 'warning',
            self::Absent => 'danger',
        };
    }
}
