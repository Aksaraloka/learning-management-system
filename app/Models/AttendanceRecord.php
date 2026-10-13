<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $attendance_session_id
 * @property int $user_id
 * @property AttendanceStatus $status
 * @property string|null $note
 */
#[Fillable(['attendance_session_id', 'user_id', 'status', 'note'])]
class AttendanceRecord extends Model
{
    protected function casts(): array
    {
        return ['status' => AttendanceStatus::class];
    }

    /**
     * @return BelongsTo<AttendanceSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'attendance_session_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
