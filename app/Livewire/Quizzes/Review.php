<?php

namespace App\Livewire\Quizzes;

use App\Actions\Quizzes\GradeEssays;
use App\Enums\AttemptStatus;
use App\Enums\QuestionType;
use App\Models\Course;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Support\QuizGrader;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Tinjauan satu percobaan. Mahasiswa melihat hasilnya sendiri (sesuai jadwal tampil nilai); dosen melihat semua jawaban
 * dan menilai esai; ketua program studi hanya melihat.
 */
class Review extends Component
{
    #[Locked]
    public Course $course;

    #[Locked]
    public Quiz $quiz;

    #[Locked]
    public QuizAttempt $attempt;

    /** @var array<int|string, string> nilai esai per soal (input) */
    public array $scores = [];

    /** @var array<int|string, string> catatan esai per soal (input) */
    public array $notes = [];

    public function mount(Course $course, Quiz $quiz, QuizAttempt $attempt): void
    {
        abort_unless($quiz->course_id === $course->id && $attempt->quiz_id === $quiz->id, 404);

        Gate::authorize('view', $attempt);

        $this->course = $course;
        $this->quiz = $quiz;
        $this->attempt = $attempt;

        // Percobaan yang masih berjalan dikerjakan di halaman pengerjaan, bukan dilihat orang lain.
        if ($attempt->isInProgress()) {
            abort_unless($attempt->user_id === Auth::id(), 403);

            $this->redirectRoute('quizzes.attempt', ['course' => $course, 'quiz' => $quiz, 'attempt' => $attempt]);

            return;
        }

        foreach ($attempt->manual_scores ?? [] as $questionId => $entry) {
            $this->scores[$questionId] = rtrim(rtrim(number_format((float) $entry['score'], 2, '.', ''), '0'), '.');
            $this->notes[$questionId] = (string) ($entry['feedback'] ?? '');
        }
    }

    public function saveGrades(GradeEssays $gradeEssays): void
    {
        Gate::authorize('grade', $this->attempt);

        $scores = [];

        foreach ($this->scores as $questionId => $value) {
            $scores[(int) $questionId] = trim((string) $value) === '' ? null : (is_numeric($value) ? (float) $value : -1.0);
        }

        $this->attempt = $gradeEssays->handle($this->attempt, $scores, array_map(fn ($n) => (string) $n, $this->notes));

        $this->dispatch('toast', message: __('quizzes.grade.saved'), type: 'success');
    }

    public function render(): View
    {
        $attempt = $this->attempt->fresh(['user']);
        $isOwner = $attempt->user_id === Auth::id();
        $canGrade = Gate::allows('grade', $attempt);
        $isStaff = Gate::allows('viewResults', $this->quiz);

        // Mahasiswa: nilai tampil sesuai jadwal; kunci jawaban baru terbuka bila kuis sudah ditutup atau percobaan habis.
        $used = QuizAttempt::query()->where('quiz_id', $this->quiz->id)->where('user_id', $attempt->user_id)->count();
        $resultsVisible = $isStaff || $this->quiz->resultsVisible();
        $keyVisible = $isStaff || ($resultsVisible && ($this->quiz->hasClosed() || $used >= $this->quiz->max_attempts));

        $questions = Question::query()->where('quiz_id', $this->quiz->id)->whereIn('id', $attempt->question_order)->get()->keyBy('id');
        $grade = QuizGrader::grade($questions->values(), $attempt);
        $optionOrder = $attempt->option_order ?? [];

        $rows = collect($attempt->question_order)->map(fn ($id) => $questions->get((int) $id))->filter()->values()
            ->map(function (Question $q, int $i) use ($attempt, $grade, $keyVisible, $optionOrder, $isStaff, $resultsVisible) {
                $answer = $attempt->answers[(string) $q->id] ?? null;
                $per = $grade['perQuestion'][$q->id] ?? ['answered' => false, 'correct' => null, 'earned' => null];
                $options = $q->type === QuestionType::MultipleChoice ? $q->optionsFor($optionOrder[(string) $q->id] ?? null) : [];

                return [
                    'number' => $i + 1,
                    'question' => $q,
                    'image' => $q->hasImage() ? route('questions.image', $q) : null,
                    'options' => $options,
                    'answer' => $answer,
                    'answerText' => $this->answerText($q, $answer, $options),
                    'correctText' => $keyVisible ? $this->correctText($q, $options) : null,
                    'correct' => $resultsVisible ? $per['correct'] : null,
                    'earned' => $resultsVisible ? $per['earned'] : null,
                    'manual' => $resultsVisible ? ($attempt->manual_scores[(string) $q->id] ?? null) : null,
                    'rubric' => $isStaff && $q->type === QuestionType::Essay ? ($q->answer_key['rubric'] ?? null) : null,
                    'flagged' => in_array($q->id, array_map('intval', $attempt->flags ?? []), true),
                ];
            });

        return view('livewire.quizzes.review', [
            'attempt' => $attempt,
            'rows' => $rows,
            'isOwner' => $isOwner,
            'isStaff' => $isStaff,
            'canGrade' => $canGrade,
            'resultsVisible' => $resultsVisible,
            'showScore' => $resultsVisible && $attempt->status === AttemptStatus::Graded,
            'pending' => $attempt->status === AttemptStatus::Submitted,
            'locked' => (bool) $this->quiz->course()->first()?->term()->first()?->is_locked,
        ])->title($this->quiz->title);
    }

    /**
     * @param  list<array{id: string, text: string}>  $options
     */
    private function answerText(Question $q, mixed $answer, array $options): ?string
    {
        if (! QuizGrader::isAnswered($answer)) {
            return null;
        }

        return match ($q->type) {
            QuestionType::MultipleChoice => collect($options)->firstWhere('id', (string) $answer)['text'] ?? null,
            QuestionType::TrueFalse => $answer === 'true' ? __('quizzes.question.true') : __('quizzes.question.false'),
            default => (string) $answer,
        };
    }

    /**
     * @param  list<array{id: string, text: string}>  $options
     */
    private function correctText(Question $q, array $options): ?string
    {
        $key = $q->answer_key ?? [];

        return match ($q->type) {
            QuestionType::MultipleChoice => collect($options)->firstWhere('id', (string) ($key['correct'] ?? ''))['text'] ?? null,
            QuestionType::TrueFalse => ($key['correct'] ?? true) ? __('quizzes.question.true') : __('quizzes.question.false'),
            QuestionType::ShortAnswer => implode(' / ', $key['accepted'] ?? []),
            QuestionType::Essay => null,
        };
    }
}
