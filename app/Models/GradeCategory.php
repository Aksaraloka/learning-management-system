<?php

namespace App\Models;

use App\Enums\AcademicGradeComponent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $course_id
 * @property string $name
 * @property int $weight
 * @property AcademicGradeComponent|null $academic_component
 * @property int $position
 */
#[Fillable(['course_id', 'name', 'weight', 'academic_component', 'position'])]
class GradeCategory extends Model
{
    /** Kategori bawaan tiap kelas: [nama, bobot %, komponen Academic (null = tidak dikirim)]. */
    public const DEFAULTS = [['Tugas', 40, 'assignment'], ['Kuis', 30, 'assignment'], ['Ujian', 30, null]];

    protected function casts(): array
    {
        return ['academic_component' => AcademicGradeComponent::class];
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
