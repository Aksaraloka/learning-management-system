<?php

namespace App\Livewire\Courses;

use App\Actions\Courses\EnrollMembers;
use App\Enums\EnrollmentRole;
use App\Models\Course;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Members extends Component
{
    #[Locked]
    public Course $course;

    public string $role = 'student';

    public string $identifiers = '';

    /** @var array{added: int, missing: list<string>, ineligible: list<string>, skipped: list<string>}|null */
    public ?array $result = null;

    public function mount(Course $course): void
    {
        Gate::authorize('viewRoster', $course);

        $this->course = $course;
    }

    public function add(EnrollMembers $enroll): void
    {
        Gate::authorize('manageMembers', $this->course);

        $this->validate([
            'role' => ['required', 'in:student,teacher'],
            'identifiers' => ['required', 'string', 'max:20000'],
        ], [], ['identifiers' => __('courses.members.identifiers')]);

        $list = preg_split('/[\r\n,;]+/', $this->identifiers) ?: [];

        $this->result = $enroll->handle($this->course, EnrollmentRole::from($this->role), $list);

        if ($this->result['added'] > 0) {
            $this->reset('identifiers');
        }
    }

    public function remove(int $userId): void
    {
        Gate::authorize('manageMembers', $this->course);

        $this->course->enrollments()->where('user_id', $userId)->delete();
    }

    public function regenerateCode(): void
    {
        Gate::authorize('manageMembers', $this->course);

        $this->course->update(['code' => Course::generateCode()]);

        $this->dispatch('toast', message: __('courses.members.code_regenerated'), type: 'success');
    }

    public function render(): View
    {
        return view('livewire.courses.members', [
            'teachers' => $this->course->teachers()->get(),
            'students' => $this->course->students()->get(),
            'canManage' => Gate::allows('manageMembers', $this->course),
        ])->title(__('courses.members.title').' · '.$this->course->name);
    }
}
