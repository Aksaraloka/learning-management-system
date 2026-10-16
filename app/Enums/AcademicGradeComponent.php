<?php

namespace App\Enums;

/**
 * Komponen nilai di Academic yang dapat dikirim dari Learn. Kategori nilai tanpa komponen (null) tidak dikirim.
 */
enum AcademicGradeComponent: string
{
    case Assignment = 'assignment';
    case Midterm = 'midterm';
    case Final = 'final';

    public function label(): string
    {
        return __('gradebook.components.'.$this->value);
    }
}
