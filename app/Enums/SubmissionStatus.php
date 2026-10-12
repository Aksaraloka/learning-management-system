<?php

namespace App\Enums;

/**
 * Status pengumpulan tugas. NotSubmitted tidak pernah disimpan (tidak ada baris = belum mengumpulkan).
 */
enum SubmissionStatus: string
{
    case NotSubmitted = 'not_submitted';
    case Submitted = 'submitted';
    case Late = 'late';
    case Graded = 'graded';
    case Returned = 'returned';

    public function label(): string
    {
        return __('ui.status.'.$this->value);
    }

    /** Menunggu penilaian dosen. */
    public function needsGrading(): bool
    {
        return in_array($this, [self::Submitted, self::Late], true);
    }

    /**
     * @return list<string>
     */
    public static function awaitingGrading(): array
    {
        return [self::Submitted->value, self::Late->value];
    }
}
