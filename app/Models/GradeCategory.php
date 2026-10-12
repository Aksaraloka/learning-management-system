<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $course_id
 * @property string $name
 * @property int $weight
 * @property int $position
 */
#[Fillable(['course_id', 'name', 'weight', 'position'])]
class GradeCategory extends Model
{
    /** Kategori bawaan tiap kelas: [nama, bobot %]. */
    public const DEFAULTS = [['Tugas', 40], ['Kuis', 30], ['Ujian', 30]];

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
