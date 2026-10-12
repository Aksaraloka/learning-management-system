<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $assignment_id
 * @property int $user_id
 * @property string $body
 * @property bool $is_private
 * @property int|null $thread_user_id
 * @property Carbon $created_at
 * @property-read User $user
 */
#[Fillable(['assignment_id', 'user_id', 'body', 'is_private', 'thread_user_id'])]
class AssignmentComment extends Model
{
    protected function casts(): array
    {
        return ['is_private' => 'boolean'];
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
}
