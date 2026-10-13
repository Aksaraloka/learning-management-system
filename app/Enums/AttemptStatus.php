<?php

namespace App\Enums;

enum AttemptStatus: string
{
    case InProgress = 'in_progress';
    /** Sudah dikumpulkan, menunggu penilaian esai oleh dosen. */
    case Submitted = 'submitted';
    case Graded = 'graded';

    public function label(): string
    {
        return __('quizzes.status.'.$this->value);
    }
}
