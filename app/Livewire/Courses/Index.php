<?php

namespace App\Livewire\Courses;

use App\Actions\Courses\JoinCourse;
use App\Enums\Role;
use App\Models\AcademicTerm;
use App\Models\Course;
use App\Support\CourseProgress;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $term = '';

    public string $code = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTerm(): void
    {
        $this->resetPage();
    }

    public function join(JoinCourse $joinCourse): void
    {
        Gate::authorize('join', Course::class);

        $this->validate(['code' => ['required', 'string', 'max:20']], [], ['code' => __('courses.join.code')]);

        $course = $joinCourse->handle(Auth::user(), $this->code);

        $this->redirectRoute('courses.show', $course);
    }

    public function render(): View
    {
        $courses = Course::query()
            ->visibleTo(Auth::user())
            ->with(['term', 'teachers'])
            ->withCount('students')
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.addcslashes($this->search, '%_\\').'%'))
            ->when($this->term !== '', fn ($q) => $q->where('academic_term_id', (int) $this->term))
            ->orderBy('name')
            ->simplePaginate(12);

        $user = Auth::user();

        return view('livewire.courses.index', [
            'courses' => $courses,
            'progress' => $user->hasAppRole(Role::Student) ? CourseProgress::forStudent($user, $courses->pluck('id')) : [],
            'terms' => AcademicTerm::query()->orderByDesc('starts_at')->pluck('name', 'id')->all(),
        ])->title(__('ui.nav.classes'));
    }
}
