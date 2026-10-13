<?php

namespace App\Actions\Quizzes;

use App\Enums\AttemptStatus;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Support\Audit;

class RecomputeAttempts
{
    /**
     * Menghitung ulang semua percobaan yang sudah selesai setelah kunci jawaban/poin soal diperbaiki.
     * Jawaban dan nilai manual esai dipertahankan. Mengembalikan jumlah percobaan yang diproses.
     */
    public function handle(Quiz $quiz): int
    {
        $count = 0;

        QuizAttempt::query()->where('quiz_id', $quiz->id)
            ->where('status', '!=', AttemptStatus::InProgress->value)
            ->chunkById(100, function ($attempts) use (&$count) {
                foreach ($attempts as $attempt) {
                    app(FinishAttempt::class)->apply($attempt);
                    $attempt->save();
                    $count++;
                }
            });

        Audit::record('quiz.recomputed', $quiz, ['attempts' => $count], $quiz->course_id);

        return $count;
    }
}
