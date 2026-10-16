<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\AcademicTermFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $name
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property bool $is_locked
 * @property int|null $academic_term_id
 */
#[Fillable(['name', 'starts_at', 'ends_at', 'is_locked', 'academic_term_id'])]
class AcademicTerm extends Model
{
    /** @use HasFactory<AcademicTermFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'is_locked' => 'boolean',
        ];
    }

    /** Semester hasil sinkron dimiliki Academic; hanya dikunci dari Learn saat integrasi aktif. */
    public function isManagedByAcademic(): bool
    {
        return config('academic.enabled') && $this->academic_term_id !== null;
    }

    /**
     * @return HasMany<Course, $this>
     */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }
}
