<?php

namespace App\Actions\Quizzes;

use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\QuizAttempt;
use Illuminate\Support\Facades\DB;

class SaveAttemptAnswer
{
    public const MAX_SHORT = 500;

    public const MAX_ESSAY = 20000;

    /**
     * Menyimpan satu jawaban (dipanggil otomatis setiap jawaban berubah). Hanya menerima jawaban saat percobaan
     * masih berjalan menurut jam server (toleransi QuizAttempt::GRACE_SECONDS) dan untuk soal milik percobaan ini.
     * Nilai dinormalisasi per tipe soal; jawaban kosong menghapus jawaban sebelumnya. Mengembalikan sisa detik.
     *
     * @throws AttemptClosedException
     */
    public function handle(QuizAttempt $attempt, int $questionId, mixed $value): ?int
    {
        $this->ensureWritable($attempt);

        abort_unless(in_array($questionId, array_map('intval', $attempt->question_order), true), 404);

        $question = Question::query()->where('quiz_id', $attempt->quiz_id)->findOrFail($questionId);
        $clean = $this->normalize($question, $value);

        DB::transaction(function () use ($attempt, $questionId, $clean) {
            // Baris dikunci agar dua permintaan hampir bersamaan (mis. dua tab) tidak saling menimpa.
            $fresh = QuizAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $this->ensureWritable($fresh);

            $answers = $fresh->answers ?? [];

            if ($clean === null) {
                unset($answers[(string) $questionId]);
            } else {
                $answers[(string) $questionId] = $clean;
            }

            $fresh->update(['answers' => $answers]);
        });

        return $attempt->remainingSeconds();
    }

    /**
     * Menandai/melepas tanda ragu-ragu pada sebuah soal.
     *
     * @throws AttemptClosedException
     */
    public function flag(QuizAttempt $attempt, int $questionId, bool $flagged): void
    {
        $this->ensureWritable($attempt);

        abort_unless(in_array($questionId, array_map('intval', $attempt->question_order), true), 404);

        DB::transaction(function () use ($attempt, $questionId, $flagged) {
            $fresh = QuizAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $flags = collect($fresh->flags ?? [])->map(fn ($id) => (int) $id)->reject(fn (int $id) => $id === $questionId);

            if ($flagged) {
                $flags->push($questionId);
            }

            $fresh->update(['flags' => $flags->unique()->values()->all()]);
        });
    }

    private function ensureWritable(QuizAttempt $attempt): void
    {
        if (! $attempt->isInProgress() || $attempt->isExpired()) {
            throw new AttemptClosedException;
        }
    }

    /**
     * Nilai yang tidak valid untuk tipe soal diperlakukan sebagai kosong (tidak pernah memicu error di sisi peserta).
     */
    private function normalize(Question $question, mixed $value): ?string
    {
        if (! is_string($value) && ! is_bool($value) && ! is_int($value)) {
            return null;
        }

        $value = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;

        return match ($question->type) {
            QuestionType::MultipleChoice => in_array($value, array_column($question->options ?? [], 'id'), true) ? $value : null,
            QuestionType::TrueFalse => in_array($value, ['true', 'false'], true) ? $value : null,
            QuestionType::ShortAnswer => ($v = mb_substr(trim($value), 0, self::MAX_SHORT)) !== '' ? $v : null,
            QuestionType::Essay => ($v = mb_substr($value, 0, self::MAX_ESSAY)) !== '' && trim($v) !== '' ? $v : null,
        };
    }
}
