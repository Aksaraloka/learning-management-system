<?php

namespace App\Models;

use App\Enums\EnrollmentRole;
use App\Enums\Role;
use Carbon\Carbon;
use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $academic_term_id
 * @property string $name
 * @property string $code
 * @property int|null $credits
 * @property string|null $description
 * @property Carbon $created_at
 * @property-read AcademicTerm $term
 * @property-read Collection<int, User> $teachers
 * @property-read Collection<int, Section> $sections
 * @property-read int|null $students_count
 */
#[Fillable(['academic_term_id', 'name', 'code', 'credits', 'description'])]
class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasFactory, SoftDeletes;

    /** Tanpa 0/O/1/I/L agar kode mudah dibaca dan diketik di HP. */
    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /**
     * Kode gabung acak yang belum dipakai (termasuk kelas yang sudah dihapus).
     */
    public static function generateCode(): string
    {
        do {
            $code = '';

            foreach (range(1, 6) as $_) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
        } while (self::withTrashed()->where('code', $code)->exists());

        return $code;
    }

    public static function normalizeCode(string $input): string
    {
        return Str::upper(preg_replace('/[^A-Za-z0-9]/', '', $input) ?? '');
    }

    /**
     * @return BelongsTo<AcademicTerm, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class, 'academic_term_id')->withTrashed();
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'enrollments')
            ->wherePivot('role', EnrollmentRole::Teacher->value)->withTimestamps()->orderBy('users.name');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'enrollments')
            ->wherePivot('role', EnrollmentRole::Student->value)->withTimestamps()->orderBy('users.name');
    }

    /**
     * @return HasMany<GradeCategory, $this>
     */
    public function gradeCategories(): HasMany
    {
        return $this->hasMany(GradeCategory::class)->orderBy('position');
    }

    /**
     * @return HasMany<Assignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * @return HasMany<Section, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('position');
    }

    /**
     * Kelas yang boleh dilihat pengguna: admin & kepala melihat semua, lainnya hanya yang diikuti.
     *
     * @param  Builder<Course>  $query
     * @return Builder<Course>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasAppRole(Role::Admin, Role::Principal)) {
            return $query;
        }

        return $query->whereHas('enrollments', fn (Builder $q) => $q->where('user_id', $user->id));
    }

    public function isTaughtBy(User $user): bool
    {
        return $this->enrollments()->where('user_id', $user->id)->where('role', EnrollmentRole::Teacher->value)->exists();
    }

    public function hasMember(User $user): bool
    {
        return $this->enrollments()->where('user_id', $user->id)->exists();
    }
}
