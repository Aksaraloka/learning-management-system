<?php

namespace App\Actions\Gradebook;

use App\Enums\AcademicGradeComponent;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\GradeCategory;
use App\Models\Quiz;
use App\Support\Audit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateGradeCategories
{
    /**
     * Mengubah nama dan bobot kategori nilai. Jumlah bobot harus tepat 100%. Ditolak bila semester dikunci.
     * Id kategori lain kelas diabaikan.
     *
     * @param  array<int|string, array{name: string, weight: int|string, component?: string|null}>  $entries  dikunci id kategori
     *
     * @throws AuthorizationException|ValidationException
     */
    public function handle(Course $course, array $entries): void
    {
        $this->ensureOpen($course);

        $categories = $course->gradeCategories()->get()->keyBy('id');
        $total = 0;
        $errors = [];

        foreach ($categories as $id => $category) {
            $entry = $entries[$id] ?? ['name' => $category->name, 'weight' => $category->weight];
            $name = trim((string) $entry['name']);
            $weight = $entry['weight'];

            if ($name === '' || mb_strlen($name) > 100) {
                $errors["entries.{$id}.name"] = __('gradebook.errors.name');
            }

            $component = $entry['component'] ?? null;

            if ($component !== null && $component !== '' && AcademicGradeComponent::tryFrom((string) $component) === null) {
                $errors["entries.{$id}.component"] = __('gradebook.errors.component');
            }

            if (! is_numeric($weight) || (int) $weight < 0 || (int) $weight > 100 || (string) (int) $weight !== (string) $weight) {
                $errors["entries.{$id}.weight"] = __('gradebook.errors.weight');

                continue;
            }

            $total += (int) $weight;
        }

        if (! $errors && $total !== 100) {
            $errors['weights'] = __('gradebook.errors.total', ['n' => $total]);
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $before = $categories->mapWithKeys(fn ($c) => [$c->name => $c->weight])->all();
        $beforeComponents = $categories->mapWithKeys(fn ($c) => [$c->name => $c->academic_component->value ?? '-'])->all();

        DB::transaction(function () use ($categories, $entries) {
            foreach ($categories as $id => $category) {
                $entry = $entries[$id] ?? null;

                if ($entry) {
                    $category->update([
                        'name' => trim((string) $entry['name']),
                        'weight' => (int) $entry['weight'],
                        'academic_component' => array_key_exists('component', $entry)
                            ? AcademicGradeComponent::tryFrom((string) $entry['component'])
                            : $category->academic_component,
                    ]);
                }
            }
        });

        $updated = $course->gradeCategories()->get();
        $after = $updated->mapWithKeys(fn ($c) => [$c->name => $c->weight])->all();
        $afterComponents = $updated->mapWithKeys(fn ($c) => [$c->name => $c->academic_component->value ?? '-'])->all();

        if ($before !== $after) {
            Audit::record('gradebook.weights_updated', $course, ['from' => $before, 'to' => $after], $course->id);
        }

        if ($beforeComponents !== $afterComponents) {
            Audit::record('gradebook.components_updated', $course, ['from' => $beforeComponents, 'to' => $afterComponents], $course->id);
        }
    }

    /**
     * Menambah kategori baru berbobot 0 (dosen mengatur bobot setelahnya).
     *
     * @throws AuthorizationException|ValidationException
     */
    public function add(Course $course, string $name): GradeCategory
    {
        $this->ensureOpen($course);

        $name = trim($name);

        if ($name === '' || mb_strlen($name) > 100) {
            throw ValidationException::withMessages(['newCategory' => __('gradebook.errors.name')]);
        }

        if ($course->gradeCategories()->where('name', $name)->exists()) {
            throw ValidationException::withMessages(['newCategory' => __('gradebook.errors.duplicate')]);
        }

        return $course->gradeCategories()->create(['name' => $name, 'weight' => 0, 'position' => (int) $course->gradeCategories()->max('position') + 1]);
    }

    /**
     * Menghapus kategori yang kosong (tanpa tugas/kuis).
     *
     * @throws AuthorizationException|ValidationException
     */
    public function remove(Course $course, GradeCategory $category): void
    {
        $this->ensureOpen($course);

        abort_unless($category->course_id === $course->id, 404);

        $used = Assignment::query()->where('grade_category_id', $category->id)->exists()
            || Quiz::query()->where('grade_category_id', $category->id)->exists();

        if ($used) {
            throw ValidationException::withMessages(['weights' => __('gradebook.errors.in_use', ['name' => $category->name])]);
        }

        $category->delete();
    }

    /**
     * @throws AuthorizationException
     */
    private function ensureOpen(Course $course): void
    {
        if ($course->isGradesLocked()) {
            throw new AuthorizationException(__('gradebook.locked'));
        }
    }
}
