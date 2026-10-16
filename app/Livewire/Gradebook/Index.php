<?php

namespace App\Livewire\Gradebook;

use App\Actions\Gradebook\UpdateGradeCategories;
use App\Models\Course;
use App\Support\AttendanceSummary;
use App\Support\Gradebook;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Buku nilai mata kuliah. Pengelola mengatur bobot dan melihat semua mahasiswa; ketua program studi hanya melihat;
 * mahasiswa hanya melihat nilainya sendiri (data mahasiswa lain tidak pernah dikirim ke halaman).
 */
class Index extends Component
{
    #[Locked]
    public Course $course;

    /** @var array<int|string, array{name: string, weight: string}> */
    public array $entries = [];

    public string $newCategory = '';

    public function mount(Course $course): void
    {
        Gate::authorize('view', $course);

        $this->course = $course;
        $this->loadEntries();
    }

    private function loadEntries(): void
    {
        $this->entries = $this->course->gradeCategories()->get()
            ->mapWithKeys(fn ($c) => [$c->id => ['name' => $c->name, 'weight' => (string) $c->weight]])->all();
    }

    public function saveWeights(UpdateGradeCategories $update): void
    {
        Gate::authorize('update', $this->course);

        $update->handle($this->course, $this->entries);

        $this->dispatch('toast', message: __('gradebook.saved'), type: 'success');
        $this->dispatch('close-modal', 'weights');
    }

    public function addCategory(UpdateGradeCategories $update): void
    {
        Gate::authorize('update', $this->course);

        $update->add($this->course, $this->newCategory);

        $this->newCategory = '';
        $this->loadEntries();
    }

    public function removeCategory(int $id, UpdateGradeCategories $update): void
    {
        Gate::authorize('update', $this->course);

        $update->remove($this->course, $this->course->gradeCategories()->findOrFail($id));

        $this->loadEntries();
    }

    public function render(): View
    {
        $user = Auth::user();
        $canRoster = Gate::allows('viewRoster', $this->course);
        $data = Gradebook::compute($this->course);

        if (! $canRoster) {
            // Mahasiswa: hanya barisnya sendiri.
            $data['students'] = $data['students']->where('id', $user->id)->values();
            $data['rows'] = array_intersect_key($data['rows'], [$user->id => true]);
        }

        $attendance = AttendanceSummary::forCourse($this->course);

        return view('livewire.gradebook.index', [
            'data' => $data,
            'attendance' => $canRoster ? $attendance : array_intersect_key($attendance, [$user->id => true]),
            'canRoster' => $canRoster,
            'canManage' => Gate::allows('update', $this->course),
            'locked' => $this->course->isGradesLocked(),
        ])->title(__('gradebook.title').' · '.$this->course->name);
    }
}
