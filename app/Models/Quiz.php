<?php

namespace App\Models;

use App\Enums\QuestionType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $course_id
 * @property int $section_id
 * @property int|null $grade_category_id
 * @property string $title
 * @property string|null $instructions
 * @property bool $is_published
 * @property Carbon|null $opens_at
 * @property Carbon|null $closes_at
 * @property int|null $duration_minutes
 * @property bool $shuffle_questions
 * @property bool $shuffle_options
 * @property int $max_attempts
 * @property Carbon|null $show_result_at
 * @property int $position
 * @property-read Course $course
 * @property-read Section $section
 * @property-read Collection<int, Question> $questions
 */
#[Fillable(['course_id', 'section_id', 'grade_category_id', 'title', 'instructions', 'is_published', 'opens_at', 'closes_at', 'duration_minutes', 'shuffle_questions', 'shuffle_options', 'max_attempts', 'show_result_at', 'position'])]
class Quiz extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'show_result_at' => 'datetime',
            'shuffle_questions' => 'boolean',
            'shuffle_options' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * @return BelongsTo<GradeCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(GradeCategory::class, 'grade_category_id');
    }

    /**
     * @return HasMany<Question, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<QuizAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function hasStarted(): bool
    {
        return $this->opens_at === null || now()->greaterThanOrEqualTo($this->opens_at);
    }

    public function hasClosed(): bool
    {
        return $this->closes_at !== null && now()->greaterThan($this->closes_at);
    }

    /** Terbit dan berada dalam jendela buka-tutup. */
    public function isOpen(): bool
    {
        return $this->is_published && $this->hasStarted() && ! $this->hasClosed();
    }

    /**
     * Status untuk tampilan: draft | upcoming | open | closed.
     */
    public function state(): string
    {
        return match (true) {
            ! $this->is_published => 'draft',
            ! $this->hasStarted() => 'upcoming',
            $this->hasClosed() => 'closed',
            default => 'open',
        };
    }

    /** Apakah nilai boleh ditampilkan ke peserta saat ini? */
    public function resultsVisible(): bool
    {
        return $this->show_result_at === null || now()->greaterThanOrEqualTo($this->show_result_at);
    }

    public function totalPoints(): float
    {
        return round((float) $this->questions()->sum('points'), 2);
    }

    public function hasEssay(): bool
    {
        return $this->questions()->where('type', QuestionType::Essay->value)->exists();
    }

    /**
     * Waktu terakhir percobaan yang dimulai sekarang: min(sekarang + durasi, kuis ditutup). Null = tanpa batas.
     */
    public function deadlineFor(Carbon $startedAt): ?Carbon
    {
        $byDuration = $this->duration_minutes ? $startedAt->copy()->addMinutes($this->duration_minutes) : null;

        return match (true) {
            $byDuration && $this->closes_at => $byDuration->lessThan($this->closes_at) ? $byDuration : $this->closes_at->copy(),
            $byDuration !== null => $byDuration,
            default => $this->closes_at?->copy(),
        };
    }
}
