<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $assignment_id
 * @property int $user_id
 * @property string|null $body
 * @property string|null $file_path
 * @property string|null $file_name
 * @property int|null $file_size
 * @property string|null $mime_type
 * @property SubmissionStatus $status
 * @property bool $is_late
 * @property float|null $score
 * @property string|null $feedback
 * @property Carbon|null $submitted_at
 * @property Carbon|null $graded_at
 * @property int|null $graded_by
 * @property-read Assignment $assignment
 * @property-read User $user
 */
#[Fillable(['assignment_id', 'user_id', 'body', 'file_path', 'file_name', 'file_size', 'mime_type', 'status', 'is_late', 'score', 'feedback', 'submitted_at', 'graded_at', 'graded_by'])]
class Submission extends Model
{
    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
            'is_late' => 'boolean',
            'score' => 'float',
            'submitted_at' => 'datetime',
            'graded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Assignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasContent(): bool
    {
        return trim(strip_tags((string) $this->body)) !== '' || $this->file_path !== null;
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }
}
