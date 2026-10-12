<?php

namespace App\Livewire\Courses;

use App\Actions\Materials\DeleteMaterial;
use App\Actions\Materials\SaveMaterial;
use App\Actions\Sections\ManageSections;
use App\Models\Course;
use App\Models\Material;
use App\Models\Section;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Show extends Component
{
    #[Locked]
    public Course $course;

    public string $newSectionTitle = '';

    public ?int $renamingId = null;

    public string $renamingTitle = '';

    public function mount(Course $course): void
    {
        Gate::authorize('view', $course);

        $this->course = $course;
    }

    public function addSection(ManageSections $sections): void
    {
        Gate::authorize('create', [Section::class, $this->course]);

        $this->validate(['newSectionTitle' => ['nullable', 'string', 'max:255']], [], ['newSectionTitle' => __('courses.sections.title')]);

        $sections->add($this->course, $this->newSectionTitle);

        $this->reset('newSectionTitle');
    }

    public function startRename(int $id): void
    {
        $section = $this->section($id);

        Gate::authorize('update', $section);

        $this->renamingId = $section->id;
        $this->renamingTitle = $section->title;
    }

    public function saveRename(ManageSections $sections): void
    {
        $section = $this->section((int) $this->renamingId);

        Gate::authorize('update', $section);

        $this->validate(['renamingTitle' => ['required', 'string', 'max:255']], [], ['renamingTitle' => __('courses.sections.title')]);

        $sections->rename($section, $this->renamingTitle);

        $this->reset('renamingId', 'renamingTitle');
    }

    public function cancelRename(): void
    {
        $this->reset('renamingId', 'renamingTitle');
    }

    public function deleteSection(int $id, ManageSections $sections, DeleteMaterial $deleteMaterial): void
    {
        $section = $this->section($id);

        Gate::authorize('delete', $section);

        $sections->delete($section, $deleteMaterial);
    }

    /**
     * @param  list<int|string>  $ids
     */
    public function reorderSections(array $ids, ManageSections $sections): void
    {
        Gate::authorize('update', $this->course);

        $sections->reorder($this->course, $ids);
    }

    /**
     * @param  list<int|string>  $ids
     */
    public function reorderMaterials(int $sectionId, array $ids, SaveMaterial $materials): void
    {
        $section = $this->section($sectionId);

        Gate::authorize('update', $section);

        $materials->reorder($section, $ids);
    }

    public function deleteMaterial(int $id, DeleteMaterial $deleteMaterial): void
    {
        $material = Material::query()
            ->whereIn('section_id', $this->course->sections()->select('id'))
            ->findOrFail($id);

        Gate::authorize('delete', $material);

        $deleteMaterial->handle($material);
    }

    public function deleteCourse(): void
    {
        Gate::authorize('delete', $this->course);

        $this->course->delete();

        $this->redirectRoute('courses.index');
    }

    private function section(int $id): Section
    {
        // Dibatasi ke kelas ini: id topik kelas lain menghasilkan 404.
        return $this->course->sections()->findOrFail($id);
    }

    public function render(): View
    {
        $this->course->loadMissing(['term', 'teachers']);

        return view('livewire.courses.show', [
            'sections' => $this->course->sections()->with('materials')->get(),
            'canManage' => Gate::allows('update', $this->course),
            'canRoster' => Gate::allows('viewRoster', $this->course),
            'studentCount' => $this->course->students()->count(),
        ])->title($this->course->name);
    }
}
