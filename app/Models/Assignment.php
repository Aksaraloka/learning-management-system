<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
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
 * @property Carbon|null $due_at
 * @property int $max_score
 * @property bool $allow_late
 * @property int $position
 * @property bool $is_hidden
 * @property-read Course $course
 * @property-read Section $section
 * @property-read GradeCategory|null $category
 * @property-read Collection<int, AssignmentFile> $attachments
 * @property-read Collection<int, Submission> $submissions
 * @property-read int|null $pending_count
 * @property-read int|null $submitted_count
 */
#[Fillable(['course_id', 'section_id', 'grade_category_id', 'title', 'instructions', 'due_at', 'max_score', 'allow_late', 'position', 'is_hidden'])]
class Assignment extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'allow_late' => 'boolean',
            'is_hidden' => 'boolean',
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
     * @return HasMany<AssignmentFile, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(AssignmentFile::class);
    }

    /**
     * @return HasMany<Submission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    /**
     * @return HasMany<AssignmentComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(AssignmentComment::class);
    }

    public function isOverdue(): bool
    {
        return $this->due_at !== null && now()->greaterThan($this->due_at);
    }

    /**
     * Apakah mahasiswa boleh mengumpulkan / mengganti kiriman sekarang?
     * Sudah dinilai = terkunci; dikembalikan untuk revisi = selalu boleh; selain itu patuhi tenggat.
     */
    public function acceptsSubmissionFrom(?Submission $existing): bool
    {
        if ($existing?->status === SubmissionStatus::Graded) {
            return false;
        }

        if ($existing?->status === SubmissionStatus::Returned) {
            return true;
        }

        return ! $this->isOverdue() || $this->allow_late;
    }

    /**
     * Status tugas bagi satu mahasiswa dari kiriman yang sudah dimuat (null = belum mengumpulkan).
     */
    public static function statusOf(?Submission $submission): SubmissionStatus
    {
        return $submission !== null ? $submission->status : SubmissionStatus::NotSubmitted;
    }
}
