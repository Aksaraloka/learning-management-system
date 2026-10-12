<?php

namespace App\Models;

use App\Enums\EnrollmentRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $course_id
 * @property int $user_id
 * @property EnrollmentRole $role
 */
#[Fillable(['course_id', 'user_id', 'role'])]
class Enrollment extends Model
{
    protected function casts(): array
    {
        return ['role' => EnrollmentRole::class];
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
