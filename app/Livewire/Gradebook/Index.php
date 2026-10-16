<?php

namespace App\Livewire\Gradebook;

use App\Actions\Gradebook\PushGradesToAcademic;
use App\Actions\Gradebook\UpdateGradeCategories;
use App\Enums\AcademicGradeComponent;
use App\Models\Course;
use App\Support\Academic\AcademicException;
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

    /** @var array<int|string, array{name: string, weight: string, component: string}> */
    public array $entries = [];

    /** @var array{sent: int, updated: int, skipped: list<array{user_id: int|string, name: string|null, reason: string}>, waiting: int, component: string}|null */
    public ?array $pushResult = null;

    public string $pushError = '';

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
            ->mapWithKeys(fn ($c) => [$c->id => ['name' => $c->name, 'weight' => (string) $c->weight, 'component' => $c->academic_component->value ?? '']])->all();
    }

    public function saveWeights(UpdateGradeCategories $update): void
    {
        Gate::authorize('update', $this->course);

        $update->handle($this->course, $this->entries);

        $this->dispatch('toast', message: __('gradebook.saved'), type: 'success');
        $this->dispatch('close-modal', 'weights');
    }

    /**
     * Kirim satu komponen nilai ke Academic. Pesan Academic (mis. 409 nilai terkunci) ditampilkan apa adanya.
     */
    public function pushGrades(string $component, PushGradesToAcademic $push): void
    {
        Gate::authorize('pushGrades', $this->course);

        $enum = AcademicGradeComponent::tryFrom($component);
        abort_if($enum === null, 422);

        $this->pushResult = null;
        $this->pushError = '';

        try {
            $result = $push->handle($this->course, $enum, Auth::user());
        } catch (AcademicException $e) {
            $this->pushError = __('gradebook.academic.failed', ['message' => $e->getMessage()]);
            $this->dispatch('toast', message: $this->pushError, type: 'error');

            return;
        }

        $this->pushResult = $result + ['component' => $enum->label()];

        $message = $result['sent'] === 0
            ? __('gradebook.academic.nothing', ['component' => $enum->label()])
            : __('gradebook.academic.sent', ['updated' => $result['updated'], 'component' => $enum->label()]);

        $this->dispatch('toast', message: $message, type: $result['sent'] === 0 ? 'info' : 'success');
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

        $academicComponents = [];

        if ($canRoster && Gate::allows('pushGrades', $this->course)) {
            foreach (AcademicGradeComponent::cases() as $component) {
                $names = $data['categories']->filter(fn ($c) => $c->academic_component === $component)->pluck('name')->all();

                if ($names === []) {
                    continue;
                }

                $scores = Gradebook::componentScores($this->course, $component, $data);

                $academicComponents[] = [
                    'component' => $component,
                    'categories' => $names,
                    'ready' => count(array_filter($scores, fn (array $s) => $s['score'] !== null)),
                    'waiting' => count(array_filter($scores, fn (array $s) => $s['waiting'])),
                ];
            }
        }

        $attendanceSource = AttendanceSummary::fetch($this->course);
        $attendance = $attendanceSource['rows'];

        return view('livewire.gradebook.index', [
            'data' => $data,
            'attendance' => $canRoster ? $attendance : array_intersect_key($attendance, [$user->id => true]),
            'academicComponents' => $academicComponents,
            'attendanceUnavailable' => $attendanceSource['official'] && ! $attendanceSource['available'],
            'canRoster' => $canRoster,
            'canManage' => Gate::allows('update', $this->course),
            'locked' => $this->course->isGradesLocked(),
        ])->title(__('gradebook.title').' · '.$this->course->name);
    }
}
