<?php

namespace App\Actions\Assignments;

use App\Actions\Notifications\Notifier;
use App\Models\Assignment;
use App\Models\Course;
use Carbon\CarbonInterface;
use Mews\Purifier\Facades\Purifier;

class SaveAssignment
{
    /**
     * Membuat atau memperbarui tugas. Instruksi disanitasi (Purifier). pertemuan dan kategori harus milik kelas ini
     * (divalidasi pemanggil).
     *
     * @param  array{section_id: int, grade_category_id: int|null, title: string, instructions?: string|null, due_at: CarbonInterface|null, max_score: int, allow_late: bool}  $data
     */
    public function handle(Course $course, array $data, ?Assignment $assignment = null): Assignment
    {
        $attributes = [
            'course_id' => $course->id,
            'section_id' => $data['section_id'],
            'grade_category_id' => $data['grade_category_id'],
            'title' => trim($data['title']),
            'instructions' => Purifier::clean((string) ($data['instructions'] ?? ''), 'content') ?: null,
            'due_at' => $data['due_at'],
            'max_score' => $data['max_score'],
            'allow_late' => $data['allow_late'],
        ];

        if ($assignment) {
            $assignment->update($attributes);

            return $assignment;
        }

        $created = Assignment::create($attributes + [
            'position' => (int) Assignment::query()->where('section_id', $data['section_id'])->max('position') + 1,
        ]);

        app(Notifier::class)->assignmentPosted($created);

        return $created;
    }
}
