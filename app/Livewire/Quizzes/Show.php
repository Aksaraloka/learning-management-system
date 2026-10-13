<?php

namespace App\Livewire\Quizzes;

use App\Actions\Quizzes\StartAttempt;
use App\Enums\AttemptStatus;
use App\Models\Course;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Show extends Component
{
    #[Locked]
    public Course $course;

    #[Locked]
    public Quiz $quiz;

    public function mount(Course $course, Quiz $quiz): void
    {
        abort_unless($quiz->course_id === $course->id, 404);

        Gate::authorize('view', $quiz);

        $this->course = $course;
        $this->quiz = $quiz;
    }

    public function start(StartAttempt $startAttempt): void
    {
        Gate::authorize('attempt', $this->quiz);

        $attempt = $startAttempt->handle($this->quiz, Auth::user());

        $this->redirectRoute('quizzes.attempt', ['course' => $this->course, 'quiz' => $this->quiz, 'attempt' => $attempt]);
    }

    public function togglePublish(): void
    {
        Gate::authorize('update', $this->quiz);

        $this->quiz->update(['is_published' => ! $this->quiz->is_published]);
    }

    public function deleteQuiz(): void
    {
        Gate::authorize('delete', $this->quiz);

        $this->quiz->delete();

        $this->redirectRoute('courses.show', $this->course);
    }

    public function render(): View
    {
        $user = Auth::user();
        $canManage = Gate::allows('update', $this->quiz);
        $canSeeResults = Gate::allows('viewResults', $this->quiz);
        $canAttempt = Gate::allows('attempt', $this->quiz);

        $attempts = $canAttempt
            ? QuizAttempt::query()->where('quiz_id', $this->quiz->id)->where('user_id', $user->id)->orderBy('attempt_no')->get()
            : collect();

        $running = $attempts->first(fn (QuizAttempt $a) => $a->isInProgress() && ! $a->isExpired());
        $used = $attempts->count();

        return view('livewire.quizzes.show', [
            'canManage' => $canManage,
            'canSeeResults' => $canSeeResults,
            'canAttempt' => $canAttempt,
            'attempts' => $attempts,
            'running' => $running,
            'attemptsLeft' => max(0, $this->quiz->max_attempts - $used),
            'questionCount' => $this->quiz->questions()->count(),
            'totalPoints' => $this->quiz->totalPoints(),
            'hasEssay' => $this->quiz->hasEssay(),
            'pendingGrading' => $canSeeResults
                ? QuizAttempt::query()->where('quiz_id', $this->quiz->id)->where('status', AttemptStatus::Submitted->value)->count()
                : 0,
            'state' => $this->quiz->state(),
        ])->title($this->quiz->title);
    }
}
