<?php

namespace App\Livewire\Quizzes;

use App\Actions\Quizzes\RecomputeAttempts;
use App\Enums\AttemptStatus;
use App\Models\Course;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

class Results extends Component
{
    #[Locked]
    public Course $course;

    #[Locked]
    public Quiz $quiz;

    #[Url(as: 'tampil')]
    public string $filter = 'all';

    #[Url(as: 'cari')]
    public string $search = '';

    public function mount(Course $course, Quiz $quiz): void
    {
        abort_unless($quiz->course_id === $course->id, 404);

        Gate::authorize('viewResults', $quiz);

        $this->course = $course;
        $this->quiz = $quiz;
    }

    public function recompute(RecomputeAttempts $recompute): void
    {
        Gate::authorize('update', $this->quiz);

        $n = $recompute->handle($this->quiz);

        $this->dispatch('toast', message: __('quizzes.questions.recomputed', ['n' => $n]), type: 'success');
    }

    public function render(): View
    {
        $studentCount = $this->course->students()->count();

        $attempts = QuizAttempt::query()->with('user')->where('quiz_id', $this->quiz->id)
            ->where('status', '!=', AttemptStatus::InProgress->value)
            ->when($this->filter === 'needs', fn ($q) => $q->where('status', AttemptStatus::Submitted->value))
            ->when($this->filter === 'graded', fn ($q) => $q->where('status', AttemptStatus::Graded->value))
            ->when($this->search !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u->where('name', 'like', '%'.addcslashes($this->search, '%_\\').'%')))
            ->orderByRaw("case status when 'submitted' then 0 else 1 end")->orderBy('finished_at', 'desc')
            ->get();

        $all = QuizAttempt::query()->where('quiz_id', $this->quiz->id)->where('status', '!=', AttemptStatus::InProgress->value);
        $graded = (clone $all)->where('status', AttemptStatus::Graded->value);

        return view('livewire.quizzes.results', [
            'attempts' => $attempts,
            'stats' => [
                'students' => $studentCount,
                'took' => (clone $all)->distinct()->count('user_id'),
                'running' => QuizAttempt::query()->where('quiz_id', $this->quiz->id)->where('status', AttemptStatus::InProgress->value)->count(),
                'pending' => (clone $all)->where('status', AttemptStatus::Submitted->value)->count(),
                'average' => $graded->count() ? round((float) (clone $graded)->avg('score'), 2) : null,
                'highest' => (clone $graded)->max('score'),
                'lowest' => (clone $graded)->min('score'),
            ],
            'maxScore' => $this->quiz->totalPoints(),
            'canManage' => Gate::allows('update', $this->quiz),
        ])->title(__('quizzes.results.title').' · '.$this->quiz->title);
    }
}
