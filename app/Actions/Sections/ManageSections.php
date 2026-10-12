<?php

namespace App\Actions\Sections;

use App\Actions\Materials\DeleteMaterial;
use App\Models\Course;
use App\Models\Section;
use App\Support\Terminology;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageSections
{
    /**
     * Menambah topik (sekolah) atau pertemuan (kampus, maks. config courses.max_meetings) di urutan terakhir.
     *
     * @throws ValidationException
     */
    public function add(Course $course, ?string $title = null): Section
    {
        $count = $course->sections()->count();

        if (Terminology::mode() === Terminology::CAMPUS && $count >= (int) config('courses.max_meetings')) {
            throw ValidationException::withMessages([
                'title' => __('courses.sections.max_meetings', ['max' => config('courses.max_meetings')]),
            ]);
        }

        $title = trim((string) $title);

        return $course->sections()->create([
            'title' => $title !== '' ? $title : term('section').' '.($count + 1),
            'position' => ($course->sections()->max('position') ?? 0) + 1,
        ]);
    }

    public function rename(Section $section, string $title): void
    {
        $section->update(['title' => trim($title)]);
    }

    /**
     * Menghapus topik beserta materi dan berkasnya, lalu merapikan urutan.
     */
    public function delete(Section $section, DeleteMaterial $deleteMaterial): void
    {
        DB::transaction(function () use ($section, $deleteMaterial) {
            foreach ($section->materials()->get() as $material) {
                $deleteMaterial->handle($material);
            }

            $course = $section->course_id;
            $section->delete();

            Section::query()->where('course_id', $course)->orderBy('position')->get()
                ->each(fn (Section $s, int $i) => $s->update(['position' => $i + 1]));
        });
    }

    /**
     * Urutkan ulang sesuai daftar id. Id yang bukan milik kelas ini diabaikan.
     *
     * @param  list<int|string>  $ids
     */
    public function reorder(Course $course, array $ids): void
    {
        $valid = $course->sections()->pluck('id')->all();
        $ordered = array_values(array_filter(array_map('intval', $ids), fn (int $id) => in_array($id, $valid, true)));
        $rest = array_values(array_diff($valid, $ordered));

        DB::transaction(function () use ($ordered, $rest) {
            foreach (array_merge($ordered, $rest) as $i => $id) {
                Section::query()->whereKey($id)->update(['position' => $i + 1]);
            }
        });
    }
}
