<?php

namespace App\Actions\Assignments;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Section;
use App\Support\StoredFile;
use Illuminate\Support\Facades\DB;

class CopyAssignment
{
    /**
     * Menyalin tugas (instruksi, tenggat, nilai maks., lampiran) ke kelas lain, mis. kelas paralel.
     * Kiriman mahasiswa tidak ikut disalin. Di tiap kelas tujuan tugas masuk ke pertemuan pertama (dibuat bila belum ada)
     * dan memakai kategori bernama sama (atau tanpa kategori).
     *
     * @param  iterable<Course>  $targets  kelas tujuan (sudah dipastikan boleh dikelola pemanggil)
     * @return int jumlah salinan yang dibuat
     */
    public function handle(Assignment $assignment, iterable $targets): int
    {
        $assignment->loadMissing(['attachments', 'category']);
        $copies = 0;

        foreach ($targets as $course) {
            if ($course->id === $assignment->course_id) {
                continue;
            }

            DB::transaction(function () use ($assignment, $course, &$copies) {
                $section = $course->sections()->first() ?? Section::create([
                    'course_id' => $course->id,
                    'title' => term('section').' 1',
                    'position' => 1,
                ]);

                $category = $assignment->category
                    ? $course->gradeCategories()->where('name', $assignment->category->name)->first()
                    : null;

                $copy = Assignment::create([
                    'course_id' => $course->id,
                    'section_id' => $section->id,
                    'grade_category_id' => $category?->id,
                    'title' => $assignment->title,
                    'instructions' => $assignment->instructions,
                    'due_at' => $assignment->due_at,
                    'max_score' => $assignment->max_score,
                    'allow_late' => $assignment->allow_late,
                    'position' => (int) Assignment::query()->where('section_id', $section->id)->max('position') + 1,
                ]);

                foreach ($assignment->attachments as $file) {
                    $path = StoredFile::duplicate($file->file_path, "courses/{$course->id}/assignments/{$copy->id}");

                    if ($path) {
                        $copy->attachments()->create([
                            'file_path' => $path, 'file_name' => $file->file_name,
                            'file_size' => $file->file_size, 'mime_type' => $file->mime_type,
                        ]);
                    }
                }

                $copies++;
            });
        }

        return $copies;
    }
}
