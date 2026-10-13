<?php

namespace App\Models;

use App\Enums\AttemptStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $quiz_id
 * @property int $user_id
 * @property int $attempt_no
 * @property Carbon $started_at
 * @property Carbon|null $deadline_at
 * @property Carbon|null $finished_at
 * @property array<int|string, mixed>|null $answers jawaban per id soal
 * @property list<int>|null $flags
 * @property list<int> $question_order
 * @property array<int|string, list<string>>|null $option_order urutan id pilihan per id soal
 * @property float $auto_score
 * @property array<int|string, array{score: float, feedback?: string|null}>|null $manual_scores nilai esai per id soal
 * @property float|null $score
 * @property float $max_score
 * @property AttemptStatus $status
 * @property-read Quiz $quiz
 * @property-read User $user
 */
#[Fillable(['quiz_id', 'user_id', 'attempt_no', 'started_at', 'deadline_at', 'finished_at', 'answers', 'flags', 'question_order', 'option_order', 'auto_score', 'manual_scores', 'score', 'max_score', 'status'])]
class QuizAttempt extends Model
{
    /** Toleransi (detik) untuk jawaban yang tiba sedikit setelah batas waktu karena latensi jaringan. */
    public const GRACE_SECONDS = 10;

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'deadline_at' => 'datetime',
            'finished_at' => 'datetime',
            'answers' => 'array',
            'flags' => 'array',
            'question_order' => 'array',
            'option_order' => 'array',
            'manual_scores' => 'array',
            'auto_score' => 'float',
            'score' => 'float',
            'max_score' => 'float',
            'status' => AttemptStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Quiz, $this>
     */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isInProgress(): bool
    {
        return $this->status === AttemptStatus::InProgress;
    }

    /** Sisa detik menurut jam server (null = tanpa batas waktu). */
    public function remainingSeconds(): ?int
    {
        return $this->deadline_at ? max(0, (int) now()->diffInSeconds($this->deadline_at, false)) : null;
    }

    /** Sudah melewati batas waktu + toleransi jaringan. */
    public function isExpired(): bool
    {
        return $this->deadline_at !== null && now()->greaterThan($this->deadline_at->copy()->addSeconds(self::GRACE_SECONDS));
    }
}
