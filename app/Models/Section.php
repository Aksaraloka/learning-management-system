<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Topik (sekolah) atau pertemuan ke-n (kampus).
 *
 * @property int $id
 * @property int $course_id
 * @property string $title
 * @property int $position
 * @property-read Course $course
 * @property-read Collection<int, Material> $materials
 */
#[Fillable(['course_id', 'title', 'position'])]
class Section extends Model
{
    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return HasMany<Assignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class)->orderBy('position')->orderBy('due_at');
    }

    /**
     * @return HasMany<Material, $this>
     */
    public function materials(): HasMany
    {
        return $this->hasMany(Material::class)->orderBy('position');
    }
}
