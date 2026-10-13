<?php

namespace App\Livewire\Courses;

use App\Actions\Materials\DeleteMaterial;
use App\Actions\Materials\SaveMaterial;
use App\Actions\Notifications\Notifier;
use App\Actions\Sections\ManageSections;
use App\Enums\Role;
use App\Enums\SubmissionStatus;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Material;
use App\Models\Quiz;
use App\Models\Section;
use App\Support\Audit;
use App\Support\CourseProgress;
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

    /**
     * Menyembunyikan/menampilkan materi, tugas, atau kuis bagi mahasiswa. Kuis memakai status terbit (draf = tersembunyi).
     * Hanya pengelola kelas; id dari kelas lain menghasilkan 404.
     */
    public function toggleVisibility(string $type, int $id, Notifier $notifier): void
    {
        $sectionIds = $this->course->sections()->select('id');

        $item = match ($type) {
            'material' => Material::query()->whereIn('section_id', $sectionIds)->findOrFail($id),
            'assignment' => Assignment::query()->where('course_id', $this->course->id)->findOrFail($id),
            'quiz' => Quiz::query()->where('course_id', $this->course->id)->findOrFail($id),
            default => abort(404),
        };

        Gate::authorize('update', $this->course);

        if ($item instanceof Quiz) {
            $item->update(['is_published' => ! $item->is_published]);
            $notifier->quizPublished($item->refresh());

            return;
        }

        $item->update(['is_hidden' => ! $item->is_hidden]);
    }

    public function deleteCourse(): void
    {
        Gate::authorize('delete', $this->course);

        Audit::record('course.deleted', $this->course, ['name' => $this->course->name], $this->course->id);

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
        // Materi/tugas yang disembunyikan hanya terlihat oleh pengelola kelas dan ketua program studi.
        $sections = $this->course->sections()->with(['materials' => fn ($q) => $canRoster ? $q : $q->where('is_hidden', false), 'quizzes' => fn ($q) => $canManage ? $q->withCount('questions') : $q->where('is_published', true)->withCount('questions'), 'assignments' => fn ($q) => $canRoster
            ? $q->withCount(['submissions as submitted_count' => fn ($s) => $s->whereIn('status', ['submitted', 'late', 'graded']), 'submissions as pending_count' => fn ($s) => $s->whereIn('status', SubmissionStatus::awaitingGrading())])
            : $q->where('is_hidden', false)->with(['submissions' => fn ($s) => $s->where('user_id', $user->id)])])->get();

        return view('livewire.courses.show', [
            'sections' => $sections,
            'isStudent' => ! $canRoster && $user->hasAppRole(Role::Student),
            'canManage' => $canManage,
            'canRoster' => $canRoster,
            'studentCount' => $this->course->students()->count(),
            'progress' => ! $canRoster && $user->hasAppRole(Role::Student) ? (CourseProgress::forStudent($user, [$this->course->id])[$this->course->id] ?? null) : null,
        ])->title($this->course->name);
    }
}
