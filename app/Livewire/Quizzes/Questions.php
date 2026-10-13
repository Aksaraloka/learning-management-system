<?php

namespace App\Livewire\Quizzes;

use App\Actions\Quizzes\ManageQuestions;
use App\Actions\Quizzes\RecomputeAttempts;
use App\Models\Course;
use App\Models\Quiz;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Questions extends Component
{
    #[Locked]
    public Course $course;

    #[Locked]
    public Quiz $quiz;

    public function mount(Course $course, Quiz $quiz): void
    {
        abort_unless($quiz->course_id === $course->id, 404);

        Gate::authorize('update', $quiz);

        $this->course = $course;
        $this->quiz = $quiz;
    }

    public function delete(int $id, ManageQuestions $questions): void
    {
        Gate::authorize('update', $this->quiz);

        $questions->delete($this->quiz->questions()->findOrFail($id));
    }

    /**
     * @param  list<int|string>  $ids
     */
    public function reorder(array $ids, ManageQuestions $questions): void
    {
        Gate::authorize('update', $this->quiz);

        $questions->reorder($this->quiz, $ids);
    }

    public function recompute(RecomputeAttempts $recompute): void
    {
        Gate::authorize('update', $this->quiz);

        $n = $recompute->handle($this->quiz);

        $this->dispatch('toast', message: __('quizzes.questions.recomputed', ['n' => $n]), type: 'success');
    }

    public function render(): View
    {
        return view('livewire.quizzes.questions', [
            'questions' => $this->quiz->questions()->get(),
            'hasAttempts' => $this->quiz->attempts()->exists(),
            'totalPoints' => $this->quiz->totalPoints(),
        ])->title(__('quizzes.questions.title').' · '.$this->quiz->title);
    }
}
