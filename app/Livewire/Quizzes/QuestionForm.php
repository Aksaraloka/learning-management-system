<?php

namespace App\Livewire\Quizzes;

use App\Actions\Quizzes\ManageQuestions;
use App\Enums\QuestionType;
use App\Models\Course;
use App\Models\Question;
use App\Models\Quiz;
use App\Support\Upload;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class QuestionForm extends Component
{
    use WithFileUploads;

    public const MAX_OPTIONS = 6;

    #[Locked]
    public Course $course;

    #[Locked]
    public Quiz $quiz;

    #[Locked]
    public ?Question $question = null;

    public string $type = 'multiple_choice';

    public string $body = '';

    public string $points = '1';

    /** @var list<string> */
    public array $options = ['', '', '', ''];

    public int $correctIndex = 0;

    public string $trueFalse = 'true';

    public string $accepted = '';

    public bool $caseSensitive = false;

    public string $rubric = '';

    public ?TemporaryUploadedFile $upload = null;

    public bool $removeImage = false;

    public function mount(Course $course, Quiz $quiz, ?Question $question = null): void
    {
        abort_unless($quiz->course_id === $course->id, 404);

        Gate::authorize('update', $quiz);

        $this->course = $course;
        $this->quiz = $quiz;

        if (! $question?->exists) {
            return;
        }

        abort_unless($question->quiz_id === $quiz->id, 404);

        $this->question = $question;
        $this->type = $question->type->value;
        $this->body = $question->body;
        $this->points = rtrim(rtrim(number_format($question->points, 2, '.', ''), '0'), '.');
        $key = $question->answer_key ?? [];

        match ($question->type) {
            QuestionType::MultipleChoice => $this->loadChoices($question, $key),
            QuestionType::TrueFalse => $this->trueFalse = ($key['correct'] ?? true) ? 'true' : 'false',
            QuestionType::ShortAnswer => [$this->accepted, $this->caseSensitive] = [implode("\n", $key['accepted'] ?? []), (bool) ($key['case_sensitive'] ?? false)],
            QuestionType::Essay => $this->rubric = (string) ($key['rubric'] ?? ''),
        };
    }

    /**
     * @param  array<string, mixed>  $key
     */
    private function loadChoices(Question $question, array $key): void
    {
        $this->options = array_column($question->options ?? [], 'text');
        $ids = array_column($question->options ?? [], 'id');
        $this->correctIndex = max(0, (int) array_search($key['correct'] ?? null, $ids, true));
    }

    public function addOption(): void
    {
        if (count($this->options) < self::MAX_OPTIONS) {
            $this->options[] = '';
        }
    }

    public function removeOption(int $index): void
    {
        if (count($this->options) <= 2) {
            return;
        }

        array_splice($this->options, $index, 1);

        if ($this->correctIndex >= count($this->options)) {
            $this->correctIndex = 0;
        } elseif ($index < $this->correctIndex) {
            $this->correctIndex--;
        }
    }

    public function save(ManageQuestions $questions): void
    {
        Gate::authorize('update', $this->quiz);

        $type = QuestionType::from($this->type);

        $this->validate([
            'type' => ['required', Rule::enum(QuestionType::class)],
            'body' => ['required', 'string', 'max:100000', fn ($a, $v, $fail) => trim(strip_tags((string) $v)) === '' ? $fail(__('validation.required', ['attribute' => __('quizzes.question.body')])) : null],
            'points' => ['required', 'numeric', 'between:0.25,100'],
            'options' => $type === QuestionType::MultipleChoice ? ['required', 'array', 'min:2', 'max:'.self::MAX_OPTIONS] : ['nullable'],
            'options.*' => $type === QuestionType::MultipleChoice ? ['required', 'string', 'max:500'] : ['nullable'],
            'correctIndex' => $type === QuestionType::MultipleChoice ? ['required', 'integer', 'between:0,'.(count($this->options) - 1)] : ['nullable'],
            'trueFalse' => $type === QuestionType::TrueFalse ? ['required', 'in:true,false'] : ['nullable'],
            'accepted' => $type === QuestionType::ShortAnswer ? ['required', 'string', 'max:2000'] : ['nullable'],
            'rubric' => ['nullable', 'string', 'max:2000'],
            'upload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:'.min(2048, Upload::maxKb())],
        ], [], [
            'body' => __('quizzes.question.body'),
            'points' => __('quizzes.question.points'),
            'options' => __('quizzes.question.options'),
            'options.*' => __('quizzes.question.option'),
            'accepted' => __('quizzes.question.accepted'),
            'upload' => __('quizzes.question.image'),
        ]);

        $questions->save($this->quiz, [
            'type' => $type,
            'body' => $this->body,
            'points' => (float) $this->points,
            'options' => $this->options,
            'correct_index' => $this->correctIndex,
            'true_false' => $this->trueFalse === 'true',
            'accepted' => preg_split('/\R/u', $this->accepted) ?: [],
            'case_sensitive' => $this->caseSensitive,
            'rubric' => $this->rubric,
        ], $this->upload, $this->removeImage, $this->question);

        $this->redirectRoute('quizzes.questions', ['course' => $this->course, 'quiz' => $this->quiz]);
    }

    public function render(): View
    {
        return view('livewire.quizzes.question-form', [
            'types' => collect(QuestionType::cases())->mapWithKeys(fn (QuestionType $t) => [$t->value => $t->label()])->all(),
            'maxOptions' => self::MAX_OPTIONS,
            'maxSize' => '2MB',
            'hasAttempts' => $this->quiz->attempts()->exists(),
        ])->title($this->question ? __('quizzes.question.edit') : __('quizzes.question.add'));
    }
}
