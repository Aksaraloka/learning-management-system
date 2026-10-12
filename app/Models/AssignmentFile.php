<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $assignment_id
 * @property string $file_path
 * @property string $file_name
 * @property int|null $file_size
 * @property string|null $mime_type
 * @property-read Assignment $assignment
 */
#[Fillable(['assignment_id', 'file_path', 'file_name', 'file_size', 'mime_type'])]
class AssignmentFile extends Model
{
    /**
     * @return BelongsTo<Assignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class)->withTrashed();
    }

    public function isPreviewable(): bool
    {
        return $this->mime_type === 'application/pdf' || str_starts_with((string) $this->mime_type, 'image/');
    }
}
