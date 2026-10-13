<?php

namespace App\Actions\Quizzes;

use App\Actions\Notifications\Notifier;
use App\Enums\AttemptStatus;
use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\QuizAttempt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GradeEssays
{
    /**
     * Menyimpan nilai esai. Nilai 0..poin soal. Percobaan menjadi Graded (nilai akhir terhitung) bila semua esai sudah
     * bernilai. Ditolak bila semester dikunci admin atau percobaan belum selesai.
     *
     * @param  array<int, float|null>  $scores  question_id => nilai (null = belum dinilai)
     * @param  array<int, string|null>  $feedback  question_id => catatan
     *
     * @throws AuthorizationException|ValidationException
     */
    public function handle(QuizAttempt $attempt, array $scores, array $feedback = []): QuizAttempt
    {
        $attempt->loadMissing('quiz.course.term');

        if ($attempt->quiz->course->term->is_locked) {
            throw new AuthorizationException(__('quizzes.grade.locked'));
        }

        if ($attempt->isInProgress()) {
            throw ValidationException::withMessages(['scores' => __('quizzes.grade.not_finished')]);
        }

        $essays = Question::query()->where('quiz_id', $attempt->quiz_id)
            ->whereIn('id', $attempt->question_order)->where('type', QuestionType::Essay->value)->get()->keyBy('id');

        $errors = [];
        $manual = $attempt->manual_scores ?? [];

        foreach ($scores as $questionId => $score) {
            $question = $essays->get((int) $questionId);

            if (! $question) {
                continue; // bukan esai milik percobaan ini
            }

            if ($score === null) {
                unset($manual[(string) $questionId]);

                continue;
            }

            if ($score < 0 || $score > $question->points) {
                $errors["scores.{$questionId}"] = __('quizzes.grade.score_range', ['max' => rtrim(rtrim(number_format($question->points, 2, '.', ''), '0'), '.')]);

                continue;
            }

            $note = trim((string) ($feedback[$questionId] ?? ''));
            $manual[(string) $questionId] = ['score' => round((float) $score, 2), 'feedback' => $note !== '' ? mb_substr($note, 0, 2000) : null];
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $graded = DB::transaction(function () use ($attempt, $manual) {
            $fresh = QuizAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $wasGraded = $fresh->status === AttemptStatus::Graded;
            $fresh->manual_scores = $manual ?: null;
            app(FinishAttempt::class)->apply($fresh);
            $fresh->save();

            return [$fresh, $wasGraded];
        });

        [$fresh, $wasGraded] = $graded;

        // Mahasiswa diberi tahu sekali, saat nilai akhir pertama kali terbentuk.
        if (! $wasGraded && $fresh->status === AttemptStatus::Graded) {
            app(Notifier::class)->quizGraded($fresh);
        }

        return $fresh;
    }
}
