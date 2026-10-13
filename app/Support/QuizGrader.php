<?php

namespace App\Support;

use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\QuizAttempt;
use Illuminate\Support\Collection;

/**
 * Penilaian kuis. Soal objektif dinilai otomatis dari kunci jawaban; esai memakai nilai manual dosen.
 */
class QuizGrader
{
    /**
     * @param  Collection<int, Question>  $questions  soal yang ada di percobaan ini
     * @return array{
     *     auto: float,
     *     manual: float,
     *     max: float,
     *     essayPending: bool,
     *     perQuestion: array<int, array{answered: bool, correct: bool|null, earned: float|null}>
     * }
     */
    public static function grade(Collection $questions, QuizAttempt $attempt): array
    {
        $answers = $attempt->answers ?? [];
        $manual = $attempt->manual_scores ?? [];
        $auto = 0.0;
        $manualTotal = 0.0;
        $max = 0.0;
        $pending = false;
        $per = [];

        foreach ($questions as $question) {
            $max += $question->points;
            $answer = $answers[(string) $question->id] ?? null;
            $answered = self::isAnswered($answer);

            if ($question->type === QuestionType::Essay) {
                $score = $manual[(string) $question->id]['score'] ?? null;

                if ($score === null) {
                    $pending = true;
                } else {
                    $manualTotal += min((float) $score, $question->points);
                }

                $per[$question->id] = ['answered' => $answered, 'correct' => null, 'earned' => $score === null ? null : (float) $score];

                continue;
            }

            $correct = $answered && self::isCorrect($question, (string) $answer);
            $earned = $correct ? $question->points : 0.0;
            $auto += $earned;
            $per[$question->id] = ['answered' => $answered, 'correct' => $correct, 'earned' => $earned];
        }

        return [
            'auto' => round($auto, 2),
            'manual' => round($manualTotal, 2),
            'max' => round($max, 2),
            'essayPending' => $pending,
            'perQuestion' => $per,
        ];
    }

    public static function isAnswered(mixed $answer): bool
    {
        return is_string($answer) && trim($answer) !== '';
    }

    public static function isCorrect(Question $question, string $answer): bool
    {
        $key = $question->answer_key ?? [];

        return match ($question->type) {
            QuestionType::MultipleChoice => isset($key['correct']) && $answer === (string) $key['correct'],
            QuestionType::TrueFalse => isset($key['correct']) && $answer === ((bool) $key['correct'] ? 'true' : 'false'),
            QuestionType::ShortAnswer => self::matchesShortAnswer($answer, $key),
            QuestionType::Essay => false,
        };
    }

    /**
     * Isian singkat: cocok dengan salah satu jawaban yang diterima. Spasi berlebih diabaikan; huruf besar/kecil
     * diabaikan kecuali kunci menandai case_sensitive.
     *
     * @param  array<string, mixed>  $key
     */
    private static function matchesShortAnswer(string $answer, array $key): bool
    {
        $caseSensitive = (bool) ($key['case_sensitive'] ?? false);
        $normalize = fn (string $s): string => $caseSensitive
            ? (preg_replace('/\s+/u', ' ', trim($s)) ?? '')
            : mb_strtolower(preg_replace('/\s+/u', ' ', trim($s)) ?? '');

        $given = $normalize($answer);

        foreach ((array) ($key['accepted'] ?? []) as $accepted) {
            if ($given !== '' && $given === $normalize((string) $accepted)) {
                return true;
            }
        }

        return false;
    }
}
