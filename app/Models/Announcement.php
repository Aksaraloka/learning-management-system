<?php

namespace App\Models;

use App\Enums\Role;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $course_id null = untuk seluruh universitas
 * @property int $user_id
 * @property string $title
 * @property string $body
 * @property bool $is_pinned
 * @property Carbon $created_at
 * @property-read Course|null $course
 * @property-read User $author
 */
#[Fillable(['course_id', 'user_id', 'title', 'body', 'is_pinned'])]
class Announcement extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['is_pinned' => 'boolean'];
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
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Pengumuman yang boleh dilihat pengguna: umum, plus milik kelas yang diikuti (admin & kepala: semua).
     *
     * @param  Builder<Announcement>  $query
     * @return Builder<Announcement>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasAppRole(Role::Admin, Role::Principal)) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q->whereNull('course_id')
            ->orWhereIn('course_id', Enrollment::query()->where('user_id', $user->id)->select('course_id')));
    }
}
