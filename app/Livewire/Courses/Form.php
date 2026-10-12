<?php

namespace App\Livewire\Courses;

use App\Actions\Courses\CreateCourse;
use App\Enums\Role;
use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\User;
use App\Support\Terminology;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    public ?Course $course = null;

    public string $academic_term_id = '';

    public string $name = '';

    public string $credits = '';

    public string $description = '';

    public string $teacher_id = '';

    public function mount(?Course $course = null): void
    {
        if ($course?->exists) {
            Gate::authorize('update', $course);

            $this->course = $course;
            $this->academic_term_id = (string) $course->academic_term_id;
            $this->name = $course->name;
            $this->credits = (string) ($course->credits ?? '');
            $this->description = (string) $course->description;

            return;
        }

        Gate::authorize('create', Course::class);
    }

    public function save(CreateCourse $createCourse): void
    {
        $user = Auth::user();
        $campus = Terminology::mode() === Terminology::CAMPUS;
        $needsTeacher = ! $this->course && $user->isAdmin();

        $data = $this->validate([
            'academic_term_id' => ['required', Rule::exists('academic_terms', 'id')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:255'],
            'credits' => $campus ? ['required', 'integer', 'between:1,24'] : ['nullable'],
            'description' => ['nullable', 'string', 'max:5000'],
            'teacher_id' => $needsTeacher ? ['required', 'integer', 'exists:users,id'] : ['nullable'],
        ], [], [
            'academic_term_id' => __('courses.form.term'),
            'name' => __('courses.form.name'),
            'credits' => __('courses.form.credits'),
            'teacher_id' => term('teacher'),
        ]);

        $attributes = [
            'academic_term_id' => (int) $data['academic_term_id'],
            'name' => trim($data['name']),
            'credits' => $campus ? (int) $data['credits'] : null,
            'description' => trim((string) $data['description']) ?: null,
        ];

        if ($this->course) {
            $this->course->update($attributes);
            $course = $this->course;
        } else {
            $teacher = $needsTeacher ? User::findOrFail((int) $data['teacher_id']) : $user;
            abort_unless($teacher->hasAppRole(Role::Teacher), 422);

            $course = $createCourse->handle($attributes, $teacher);
        }

        $this->redirectRoute('courses.show', $course);
    }

    public function render(): View
    {
        $user = Auth::user();

        return view('livewire.courses.form', [
            'terms' => AcademicTerm::query()
                ->where(fn ($q) => $q->where('is_locked', false)->orWhere('id', $this->course?->academic_term_id))
                ->orderByDesc('starts_at')->pluck('name', 'id')->all(),
            'teachers' => ! $this->course && $user->isAdmin()
                ? User::query()->role(Role::Teacher->value)->orderBy('name')->pluck('name', 'id')->all()
                : [],
            'campus' => Terminology::mode() === Terminology::CAMPUS,
        ])->title($this->course ? __('courses.form.edit') : __('courses.form.create'));
    }
}
