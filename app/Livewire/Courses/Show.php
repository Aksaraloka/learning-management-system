<?php

namespace App\Livewire\Courses;

use App\Actions\Materials\DeleteMaterial;
use App\Actions\Materials\SaveMaterial;
use App\Actions\Sections\ManageSections;
use App\Enums\Role;
use App\Enums\SubmissionStatus;
use App\Models\Course;
use App\Models\Material;
use App\Models\Section;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
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
        // Dibatasi ke kelas ini: id pertemuan kelas lain menghasilkan 404.
        return $this->course->sections()->findOrFail($id);
    }

    public function render(): View
    {
        $this->course->loadMissing(['term', 'teachers']);

        $user = Auth::user();
        $canManage = Gate::allows('update', $this->course);
        $canRoster = Gate::allows('viewRoster', $this->course);

        // dosen/kepala melihat jumlah kiriman; mahasiswa melihat statusnya sendiri.
        $sections = $this->course->sections()->with(['materials', 'quizzes' => fn ($q) => $canManage ? $q->withCount('questions') : $q->where('is_published', true)->withCount('questions'), 'assignments' => fn ($q) => $canRoster
            ? $q->withCount(['submissions as submitted_count' => fn ($s) => $s->whereIn('status', ['submitted', 'late', 'graded']), 'submissions as pending_count' => fn ($s) => $s->whereIn('status', SubmissionStatus::awaitingGrading())])
            : $q->with(['submissions' => fn ($s) => $s->where('user_id', $user->id)])])->get();

        return view('livewire.courses.show', [
            'sections' => $sections,
            'isStudent' => ! $canRoster && $user->hasAppRole(Role::Student),
            'canManage' => $canManage,
            'canRoster' => $canRoster,
            'studentCount' => $this->course->students()->count(),
        ])->title($this->course->name);
    }
}
