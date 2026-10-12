<?php

namespace App\Livewire\Assignments;

use App\Enums\Role;
use App\Enums\SubmissionStatus;
use App\Models\Assignment;
use App\Models\Course;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $course = '';

    public string $filter = '';

    public string $search = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['course', 'filter', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $user = Auth::user();
        $isStudent = $user->hasAppRole(Role::Student) && ! $user->hasAppRole(Role::Admin, Role::Teacher);
        $courseIds = Course::query()->visibleTo($user)->select('courses.id');

        $assignments = Assignment::query()
            ->whereIn('course_id', $courseIds)
            ->with(['course', 'category'])
            ->when($this->course !== '', fn ($q) => $q->where('course_id', (int) $this->course))
            ->when($this->search !== '', fn ($q) => $q->where('title', 'like', '%'.addcslashes($this->search, '%_\\').'%'))
            ->when($isStudent, fn ($q) => $q
                ->with(['submissions' => fn ($s) => $s->where('user_id', $user->id)])
                ->when($this->filter === 'open', fn ($q) => $q->whereDoesntHave('submissions', fn ($s) => $s->where('user_id', $user->id)))
                ->when($this->filter === 'done', fn ($q) => $q->whereHas('submissions', fn ($s) => $s->where('user_id', $user->id)->whereIn('status', ['submitted', 'late'])))
                ->when($this->filter === 'graded', fn ($q) => $q->whereHas('submissions', fn ($s) => $s->where('user_id', $user->id)->where('status', 'graded')))
                ->when($this->filter === 'revise', fn ($q) => $q->whereHas('submissions', fn ($s) => $s->where('user_id', $user->id)->where('status', 'returned'))))
            ->when(! $isStudent, fn ($q) => $q
                ->withCount(['submissions as pending_count' => fn ($s) => $s->whereIn('status', SubmissionStatus::awaitingGrading())])
                ->withCount(['submissions as submitted_count' => fn ($s) => $s->whereIn('status', ['submitted', 'late', 'graded'])])
                ->when($this->filter === 'needs', fn ($q) => $q->whereHas('submissions', fn ($s) => $s->whereIn('status', SubmissionStatus::awaitingGrading()))))
            // Tenggat terdekat dulu; tanpa tenggat paling akhir.
            ->orderByRaw('due_at is null')->orderBy('due_at')->orderBy('title')
            ->simplePaginate(15);

        $studentCounts = Course::query()->whereIn('id', $assignments->pluck('course_id')->unique())->withCount('students')->pluck('students_count', 'id');

        return view('livewire.assignments.index', [
            'assignments' => $assignments,
            'isStudent' => $isStudent,
            'studentCounts' => $studentCounts,
            'courses' => Course::query()->visibleTo($user)->orderBy('name')->pluck('name', 'id')->all(),
            'filters' => $isStudent
                ? ['open' => __('assignments.index.f_open'), 'done' => __('assignments.index.f_done'), 'revise' => __('assignments.index.f_revise'), 'graded' => __('assignments.index.f_graded')]
                : ['needs' => __('assignments.index.f_needs')],
        ])->title(__('ui.nav.tasks'));
    }
}
