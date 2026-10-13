<?php

namespace App\Enums;

enum QuestionType: string
{
    case MultipleChoice = 'multiple_choice';
    case TrueFalse = 'true_false';
    case ShortAnswer = 'short_answer';
    case Essay = 'essay';

    public function label(): string
    {
        return __('quizzes.types.'.$this->value);
    }

    /** Dinilai otomatis oleh sistem (esai dinilai manual oleh dosen). */
    public function isObjective(): bool
    {
        return $this !== self::Essay;
    }

    public function icon(): string
    {
        return match ($this) {
            self::MultipleChoice => 'list-checks',
            self::TrueFalse => 'circle-check',
            self::ShortAnswer => 'pencil',
            self::Essay => 'file-text',
        };
    }
}
