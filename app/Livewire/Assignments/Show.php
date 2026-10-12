<?php

namespace App\Livewire\Assignments;

use App\Actions\Assignments\AddComment;
use App\Actions\Assignments\CopyAssignment;
use App\Actions\Assignments\SubmitAssignment;
use App\Enums\EnrollmentRole;
use App\Enums\SubmissionStatus;
use App\Livewire\Concerns\ValidatesUploads;
use App\Models\Assignment;
use App\Models\AssignmentComment;
use App\Models\Course;
use App\Models\Submission;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Show extends Component
{
    use ValidatesUploads, WithFileUploads;

    #[Locked]
    public Course $course;

    #[Locked]
    public Assignment $assignment;

    public string $body = '';

    public ?TemporaryUploadedFile $upload = null;

    public string $commentBody = '';

    public bool $commentPrivate = false;

    /** @var list<string> */
    public array $copyTo = [];

    public function mount(Course $course, Assignment $assignment): void
    {
        abort_unless($assignment->course_id === $course->id, 404);

        Gate::authorize('view', $assignment);

        $this->course = $course;
        $this->assignment = $assignment;

        $mine = $this->mySubmission();

        // Revisi: tampilkan lagi teks kiriman sebelumnya agar mudah diperbaiki.
        if ($mine && Gate::allows('submit', $assignment) && $this->assignment->acceptsSubmissionFrom($mine)) {
            $this->body = (string) $mine->body;
        }
    }

    public function submit(SubmitAssignment $submitAssignment): void
    {
        Gate::authorize('submit', $this->assignment);

        $this->validate([
            'body' => ['nullable', 'string', 'max:200000'],
            'upload' => $this->uploadRules(),
        ], [], ['body' => __('assignments.submit.body'), 'upload' => __('assignments.submit.file')]);

        $submitAssignment->handle($this->assignment, Auth::user(), $this->body, $this->upload);

        $this->reset('upload');
        $this->dispatch('filepond-clear');
        $this->dispatch('toast', message: __('assignments.submit.done'), type: 'success');
    }

    public function postComment(AddComment $addComment): void
    {
        Gate::authorize('create', [AssignmentComment::class, $this->assignment]);

        $this->validate(['commentBody' => ['required', 'string', 'max:2000']], [], ['commentBody' => __('assignments.comments.label')]);

        $user = Auth::user();
        $isManager = Gate::allows('update', $this->assignment);

        // dosen membalas komentar privat di layar penilaian; di sini hanya komentar publik.
        $addComment->handle($this->assignment, $user, $this->commentBody, private: $this->commentPrivate && ! $isManager);

        $this->reset('commentBody', 'commentPrivate');
    }

    public function deleteComment(int $id): void
    {
        $comment = $this->assignment->comments()->findOrFail($id);

        Gate::authorize('delete', $comment);

        $comment->delete();
    }

    public function copy(CopyAssignment $copyAssignment): void
    {
        Gate::authorize('copy', $this->assignment);

        $targets = $this->copyableCourses()->whereIn('id', array_map('intval', $this->copyTo))->get();

        abort_if($targets->isEmpty(), 422);

        $count = $copyAssignment->handle($this->assignment, $targets);

        $this->reset('copyTo');
        $this->dispatch('close-modal', 'copy-assignment');
        $this->dispatch('toast', message: __('assignments.copy.done', ['n' => $count]), type: 'success');
    }

    public function deleteAssignment(): void
    {
        Gate::authorize('delete', $this->assignment);

        $this->assignment->delete();

        $this->redirectRoute('courses.show', $this->course);
    }

    private function mySubmission(): ?Submission
    {
        return Submission::query()
            ->where('assignment_id', $this->assignment->id)->where('user_id', Auth::id())->first();
    }

    /**
     * Kelas lain di semester yang sama yang boleh dikelola pengguna (kelas paralel).
     *
     * @return Builder<Course>
     */
    private function copyableCourses()
    {
        $user = Auth::user();

        return Course::query()
            ->where('academic_term_id', $this->course->academic_term_id)
            ->whereKeyNot($this->course->id)
            ->when(! $user->isAdmin(), fn ($q) => $q->whereHas('enrollments', fn ($e) => $e
                ->where('user_id', $user->id)->where('role', EnrollmentRole::Teacher->value)))
            ->orderBy('name');
    }

    public function render(): View
    {
        $user = Auth::user();
        $assignment = $this->assignment->load(['attachments', 'category', 'section']);
        $canManage = Gate::allows('update', $assignment);
        $canSeeSubmissions = Gate::allows('viewSubmissions', $assignment);
        $mine = $this->mySubmission();
        $canSubmit = Gate::allows('submit', $assignment);

        $comments = $assignment->comments()->with('user')
            ->where(function ($q) use ($user, $canSeeSubmissions) {
                $q->where('is_private', false);

                if (! $canSeeSubmissions) {
                    $q->orWhere(fn ($p) => $p->where('is_private', true)->where('thread_user_id', $user->id));
                }
            })
            ->oldest()->get();

        $stats = $canSeeSubmissions ? [
            'students' => $this->course->students()->count(),
            'submitted' => $assignment->submissions()->whereIn('status', ['submitted', 'late', 'graded'])->count(),
            'pending' => $assignment->submissions()->whereIn('status', SubmissionStatus::awaitingGrading())->count(),
        ] : null;

        return view('livewire.assignments.show', [
            'canManage' => $canManage,
            'canGrade' => Gate::allows('grade', $assignment),
            'canSeeSubmissions' => $canSeeSubmissions,
            'canSubmit' => $canSubmit,
            'mine' => $mine,
            'status' => Assignment::statusOf($mine),
            'open' => $canSubmit && $assignment->acceptsSubmissionFrom($mine),
            'comments' => $comments,
            'stats' => $stats,
            'copyable' => $canManage ? $this->copyableCourses()->pluck('name', 'id')->all() : [],
        ])->title($assignment->title);
    }
}
