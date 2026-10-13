<?php

namespace App\Livewire\Quizzes;

use App\Actions\Quizzes\SaveQuiz;
use App\Models\Course;
use App\Models\Quiz;
use App\Support\LocalTime;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Form extends Component
{
    #[Locked]
    public Course $course;

    #[Locked]
    public ?Quiz $quiz = null;

    public string $section_id = '';

    public string $grade_category_id = '';

    public string $title = '';

    public string $instructions = '';

    public string $opens_at = '';

    public string $closes_at = '';

    public string $duration_minutes = '';

    public string $max_attempts = '1';

    public bool $shuffle_questions = false;

    public bool $shuffle_options = false;

    public string $show_result_at = '';

    public bool $is_published = false;

    public function mount(Course $course, ?Quiz $quiz = null): void
    {
        $this->course = $course;

        if ($quiz?->exists) {
            abort_unless($quiz->course_id === $course->id, 404);
            Gate::authorize('update', $quiz);

            $this->quiz = $quiz;
            $this->section_id = (string) $quiz->section_id;
            $this->grade_category_id = (string) $quiz->grade_category_id;
            $this->title = $quiz->title;
            $this->instructions = (string) $quiz->instructions;
            $this->opens_at = LocalTime::format($quiz->opens_at);
            $this->closes_at = LocalTime::format($quiz->closes_at);
            $this->duration_minutes = (string) ($quiz->duration_minutes ?? '');
            $this->max_attempts = (string) $quiz->max_attempts;
            $this->shuffle_questions = $quiz->shuffle_questions;
            $this->shuffle_options = $quiz->shuffle_options;
            $this->show_result_at = LocalTime::format($quiz->show_result_at);
            $this->is_published = $quiz->is_published;

            return;
        }

        Gate::authorize('create', [Quiz::class, $course]);

        $this->section_id = (string) request()->query('pertemuan', '');
        $this->grade_category_id = (string) ($course->gradeCategories()->where('name', 'Kuis')->value('id') ?? '');
    }

    public function save(SaveQuiz $saveQuiz): void
    {
        Gate::authorize($this->quiz ? 'update' : 'create', $this->quiz ?? [Quiz::class, $this->course]);

        $this->validate([
            'section_id' => ['required', Rule::in($this->course->sections()->pluck('id')->map(fn ($id) => (string) $id)->all())],
            'grade_category_id' => ['nullable', Rule::in($this->course->gradeCategories()->pluck('id')->map(fn ($id) => (string) $id)->all())],
            'title' => ['required', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:200000'],
            'opens_at' => ['nullable', 'date_format:'.LocalTime::FORMAT],
            'closes_at' => ['nullable', 'date_format:'.LocalTime::FORMAT, $this->opens_at !== '' ? 'after:opens_at' : 'nullable'],
            'duration_minutes' => ['nullable', 'integer', 'between:1,600'],
            'max_attempts' => ['required', 'integer', 'between:1,10'],
            'show_result_at' => ['nullable', 'date_format:'.LocalTime::FORMAT],
            'shuffle_questions' => ['boolean'],
            'shuffle_options' => ['boolean'],
            'is_published' => ['boolean'],
        ], [], [
            'section_id' => term('section'),
            'title' => __('quizzes.form.title'),
            'opens_at' => __('quizzes.form.opens_at'),
            'closes_at' => __('quizzes.form.closes_at'),
            'duration_minutes' => __('quizzes.form.duration'),
            'max_attempts' => __('quizzes.form.max_attempts'),
            'show_result_at' => __('quizzes.form.show_result_at'),
        ]);

        $saved = $saveQuiz->handle($this->course, [
            'section_id' => (int) $this->section_id,
            'grade_category_id' => $this->grade_category_id !== '' ? (int) $this->grade_category_id : null,
            'title' => $this->title,
            'instructions' => $this->instructions,
            'is_published' => $this->is_published,
            'opens_at' => LocalTime::parse($this->opens_at),
            'closes_at' => LocalTime::parse($this->closes_at),
            'duration_minutes' => $this->duration_minutes !== '' ? (int) $this->duration_minutes : null,
            'shuffle_questions' => $this->shuffle_questions,
            'shuffle_options' => $this->shuffle_options,
            'max_attempts' => (int) $this->max_attempts,
            'show_result_at' => LocalTime::parse($this->show_result_at),
        ], $this->quiz);

        // Kuis baru: lanjut menyusun soal.
        $this->redirectRoute($this->quiz ? 'quizzes.show' : 'quizzes.questions', ['course' => $this->course, 'quiz' => $saved]);
    }

    public function render(): View
    {
        return view('livewire.quizzes.form', [
            'sections' => $this->course->sections()->pluck('title', 'id')->all(),
            'categories' => $this->course->gradeCategories()->pluck('name', 'id')->all(),
        ])->title($this->quiz ? __('quizzes.form.edit') : __('quizzes.form.create'));
    }
}
