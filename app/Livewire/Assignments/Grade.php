<?php

namespace App\Livewire\Assignments;

use App\Actions\Assignments\AddComment;
use App\Actions\Assignments\GradeSubmission;
use App\Enums\SubmissionStatus;
use App\Models\Assignment;
use App\Models\AssignmentComment;
use App\Models\Course;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

class Grade extends Component
{
    #[Locked]
    public Course $course;

    #[Locked]
    public Assignment $assignment;

    #[Url(as: 'siswa')]
    public ?int $studentId = null;

    #[Url(as: 'cari')]
    public string $search = '';

    #[Url(as: 'tampil')]
    public string $filter = 'all';

    public string $score = '';

    public string $feedback = '';

    public string $reply = '';

    public function mount(Course $course, Assignment $assignment): void
    {
        abort_unless($assignment->course_id === $course->id, 404);

        Gate::authorize('viewSubmissions', $assignment);

        $this->course = $course;
        $this->assignment = $assignment;

        if ($this->studentId !== null) {
            $this->select($this->studentId);
        }
    }

    public function select(?int $id): void
    {
        if ($id === null) {
            $this->reset('studentId', 'score', 'feedback', 'reply');

            return;
        }

        // Hanya peserta kelas ini; id siswa lain menghasilkan 404.
        $student = $this->course->students()->findOrFail($id);
        $submission = $this->submissionOf($student->id);

        $this->studentId = $student->id;
        $this->score = $submission?->score !== null ? rtrim(rtrim(number_format($submission->score, 2, '.', ''), '0'), '.') : '';
        $this->feedback = (string) $submission?->feedback;
        $this->reply = '';
        $this->resetValidation();
    }

    public function save(GradeSubmission $grade, bool $advance = false): void
    {
        Gate::authorize('grade', $this->assignment);
        abort_if($this->studentId === null, 404);

        $student = $this->course->students()->findOrFail($this->studentId);

        $this->validate([
            'score' => ['required', 'numeric', 'min:0', 'max:'.$this->assignment->max_score],
            'feedback' => ['nullable', 'string', 'max:5000'],
        ], [], ['score' => __('assignments.grade.score'), 'feedback' => __('assignments.grade.feedback')]);

        $grade->handle($this->assignment, $student, (float) $this->score, $this->feedback, Auth::user());

        $this->dispatch('toast', message: __('assignments.grade.saved'), type: 'success');

        if ($advance) {
            $next = $this->roster()->first(fn (array $row) => $row['status']->needsGrading() && $row['student']->id !== $student->id);

            $next ? $this->select($next['student']->id) : $this->select($student->id);
        }
    }

    public function saveAndNext(GradeSubmission $grade): void
    {
        $this->save($grade, advance: true);
    }

    public function returnForRevision(GradeSubmission $grade): void
    {
        Gate::authorize('grade', $this->assignment);
        abort_if($this->studentId === null, 404);

        $submission = $this->submissionOf($this->studentId);

        abort_unless($submission && $submission->hasContent(), 422);

        $this->validate(['feedback' => ['nullable', 'string', 'max:5000']], [], ['feedback' => __('assignments.grade.feedback')]);

        $grade->returnForRevision($this->assignment, $submission, $this->feedback);

        $this->score = '';
        $this->dispatch('toast', message: __('assignments.grade.returned'), type: 'success');
    }

    public function sendReply(AddComment $addComment): void
    {
        Gate::authorize('update', $this->assignment);
        abort_if($this->studentId === null, 404);

        $student = $this->course->students()->findOrFail($this->studentId);

        $this->validate(['reply' => ['required', 'string', 'max:2000']], [], ['reply' => __('assignments.comments.label')]);

        $addComment->handle($this->assignment, Auth::user(), $this->reply, private: true, threadStudent: $student);

        $this->reset('reply');
    }

    private function submissionOf(int $studentId): ?Submission
    {
        return Submission::query()->where('assignment_id', $this->assignment->id)->where('user_id', $studentId)->first();
    }

    /**
     * Daftar siswa beserta statusnya; yang perlu dinilai di urutan teratas.
     *
     * @return Collection<int, array{student: User, submission: Submission|null, status: SubmissionStatus}>
     */
    private function roster(): Collection
    {
        $submissions = Submission::query()->where('assignment_id', $this->assignment->id)->get()->keyBy('user_id');
        $priority = [SubmissionStatus::Late->value => 0, SubmissionStatus::Submitted->value => 0, SubmissionStatus::Returned->value => 2, SubmissionStatus::NotSubmitted->value => 1, SubmissionStatus::Graded->value => 3];

        return $this->course->students()->get()
            ->map(fn (User $student) => [
                'student' => $student,
                'submission' => $submissions->get($student->id),
                'status' => Assignment::statusOf($submissions->get($student->id)),
            ])
            ->sortBy(fn (array $row) => [$priority[$row['status']->value], mb_strtolower($row['student']->name)])
            ->values();
    }

    /**
     * Apakah baris siswa lolos pencarian nama dan filter status saat ini?
     *
     * @param  array{student: User, submission: Submission|null, status: SubmissionStatus}  $row
     */
    private function isVisible(array $row): bool
    {
        if ($this->search !== '' && ! str_contains(mb_strtolower($row['student']->name), mb_strtolower($this->search))) {
            return false;
        }

        return match ($this->filter) {
            'needs' => $row['status']->needsGrading(),
            'graded' => $row['status'] === SubmissionStatus::Graded,
            'missing' => $row['status'] === SubmissionStatus::NotSubmitted,
            default => true,
        };
    }

    public function render(): View
    {
        $roster = $this->roster();

        $counts = [
            'all' => $roster->count(),
            'needs' => $roster->filter(fn (array $r): bool => $r['status']->needsGrading())->count(),
            'graded' => $roster->filter(fn (array $r): bool => $r['status'] === SubmissionStatus::Graded)->count(),
            'missing' => $roster->filter(fn (array $r): bool => $r['status'] === SubmissionStatus::NotSubmitted)->count(),
        ];

        $visible = $roster->filter(fn (array $r): bool => $this->isVisible($r));

        $current = $this->studentId ? $roster->first(fn ($r) => $r['student']->id === $this->studentId) : null;

        $thread = $current
            ? AssignmentComment::query()->with('user')
                ->where('assignment_id', $this->assignment->id)->where('is_private', true)->where('thread_user_id', $current['student']->id)
                ->oldest()->get()
            : collect();

        $this->assignment->loadMissing('course.term');

        return view('livewire.assignments.grade', [
            'roster' => $visible,
            'counts' => $counts,
            'current' => $current,
            'thread' => $thread,
            'canGrade' => Gate::allows('grade', $this->assignment),
            'locked' => (bool) $this->assignment->course->term->is_locked,
        ])->title(__('assignments.grade.title').' · '.$this->assignment->title);
    }
}
