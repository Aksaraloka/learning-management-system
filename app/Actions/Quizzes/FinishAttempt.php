<?php

namespace App\Actions\Quizzes;

use App\Enums\AttemptStatus;
use App\Models\Question;
use App\Models\QuizAttempt;
use App\Support\QuizGrader;
use Illuminate\Support\Facades\DB;

class FinishAttempt
{
    /**
     * Menyelesaikan percobaan (idempoten): menilai soal objektif, lalu status menjadi Graded bila tidak ada esai yang
     * menunggu, atau Submitted bila ada. Waktu selesai tidak pernah melebihi batas waktu percobaan.
     */
    public function handle(QuizAttempt $attempt): QuizAttempt
    {
        return DB::transaction(function () use ($attempt) {
            $fresh = QuizAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if (! $fresh->isInProgress()) {
                return $fresh;
            }

            $finishedAt = $fresh->deadline_at && now()->greaterThan($fresh->deadline_at) ? $fresh->deadline_at : now();

            $fresh->forceFill(['finished_at' => $finishedAt]);
            $this->apply($fresh);
            $fresh->save();

            return $fresh;
        });
    }

    /**
     * Menghitung ulang nilai dan status dari jawaban + nilai manual yang ada (tanpa menyimpan).
     * Dipakai saat selesai, saat esai dinilai, dan saat kunci jawaban diperbaiki.
     */
    public function apply(QuizAttempt $attempt): void
    {
        $questions = Question::query()->where('quiz_id', $attempt->quiz_id)->whereIn('id', $attempt->question_order)->get();
        $result = QuizGrader::grade($questions, $attempt);

        $attempt->auto_score = $result['auto'];
        $attempt->max_score = $result['max'];
        $attempt->status = $result['essayPending'] ? AttemptStatus::Submitted : AttemptStatus::Graded;
        $attempt->score = $result['essayPending'] ? null : round($result['auto'] + $result['manual'], 2);
    }
}
