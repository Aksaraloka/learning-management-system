<?php

namespace App\Actions\Quizzes;

use App\Enums\AttemptStatus;
use App\Enums\QuestionType;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StartAttempt
{
    /**
     * Memulai percobaan baru, atau melanjutkan yang sedang berjalan. Aturan (semua menurut jam server): kuis terbit,
     * dalam jendela buka-tutup, punya soal, dan sisa percobaan masih ada. Urutan soal dan pilihan diacak di sini bila
     * diaktifkan, lalu disimpan agar tetap sama saat halaman dimuat ulang. Batas waktu = min(mulai + durasi, kuis ditutup).
     *
     * @throws ValidationException
     */
    public function handle(Quiz $quiz, User $student): QuizAttempt
    {
        try {
            return $this->start($quiz, $student);
        } catch (UniqueConstraintViolationException) {
            // Dua permintaan mulai bersamaan: yang kalah memakai percobaan yang baru dibuat.
            return QuizAttempt::query()->where('quiz_id', $quiz->id)->where('user_id', $student->id)
                ->where('status', AttemptStatus::InProgress->value)->latest('id')->firstOrFail();
        }
    }

    private function start(Quiz $quiz, User $student): QuizAttempt
    {
        return DB::transaction(function () use ($quiz, $student) {
            $running = QuizAttempt::query()->where('quiz_id', $quiz->id)->where('user_id', $student->id)
                ->where('status', AttemptStatus::InProgress->value)->lockForUpdate()->first();

            if ($running) {
                if (! $running->isExpired()) {
                    return $running;
                }

                app(FinishAttempt::class)->handle($running);
            }

            $this->ensureCanStart($quiz);

            $questions = $quiz->questions()->get();

            if ($questions->isEmpty()) {
                throw $this->error('no_questions');
            }

            $used = QuizAttempt::query()->where('quiz_id', $quiz->id)->where('user_id', $student->id)->count();

            if ($used >= $quiz->max_attempts) {
                throw $this->error('no_attempts');
            }

            $order = $questions->pluck('id')->all();

            if ($quiz->shuffle_questions) {
                shuffle($order);
            }

            $optionOrder = [];

            if ($quiz->shuffle_options) {
                foreach ($questions->where('type', QuestionType::MultipleChoice) as $question) {
                    $ids = array_column($question->options ?? [], 'id');
                    shuffle($ids);
                    $optionOrder[(string) $question->id] = $ids;
                }
            }

            $startedAt = now();

            return QuizAttempt::create([
                'quiz_id' => $quiz->id,
                'user_id' => $student->id,
                'attempt_no' => $used + 1,
                'started_at' => $startedAt,
                'deadline_at' => $quiz->deadlineFor($startedAt),
                'question_order' => $order,
                'option_order' => $optionOrder ?: null,
                'answers' => [],
                'flags' => [],
                'max_score' => round((float) $questions->sum('points'), 2),
                'status' => AttemptStatus::InProgress,
            ]);
        });
    }

    private function ensureCanStart(Quiz $quiz): void
    {
        if (! $quiz->is_published) {
            throw $this->error('not_published');
        }

        if (! $quiz->hasStarted()) {
            throw $this->error('not_started');
        }

        if ($quiz->hasClosed()) {
            throw $this->error('closed');
        }
    }

    private function error(string $key): ValidationException
    {
        return ValidationException::withMessages(['quiz' => __('quizzes.errors.'.$key)]);
    }

    /** Dipakai test/komponen untuk id acak pendek yang aman dibaca. */
    public static function randomId(): string
    {
        return Str::lower(Str::random(8));
    }
}
