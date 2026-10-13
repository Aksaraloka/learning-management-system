<?php

namespace App\Livewire\Quizzes;

use App\Actions\Quizzes\AttemptClosedException;
use App\Actions\Quizzes\FinishAttempt;
use App\Actions\Quizzes\SaveAttemptAnswer;
use App\Models\Course;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;
use Livewire\Component;

/**
 * Halaman pengerjaan. Seluruh soal dimuat sekali dan pindah soal terjadi di peramban (hemat data, tetap berjalan
 * saat koneksi putus). Jawaban dikirim satu per satu lewat saveAnswer() yang Renderless (tanpa render ulang) dan
 * dibackup di localStorage oleh komponen Alpine `quizAttempt`. Server menjadi acuan waktu: sisa detik dihitung
 * dari deadline_at di database. Kunci jawaban tidak pernah masuk ke halaman ini.
 */
class Attempt extends Component
{
    #[Locked]
    public Course $course;

    #[Locked]
    public Quiz $quiz;

    #[Locked]
    public QuizAttempt $attempt;

    public function mount(Course $course, Quiz $quiz, QuizAttempt $attempt, FinishAttempt $finish): void
    {
        abort_unless($quiz->course_id === $course->id && $attempt->quiz_id === $quiz->id, 404);

        Gate::authorize('answer', $attempt);

        $this->course = $course;
        $this->quiz = $quiz;
        $this->attempt = $attempt;

        // Waktu habis (mis. peramban ditutup): selesaikan dan tampilkan hasilnya.
        if ($attempt->isInProgress() && $attempt->isExpired()) {
            $finish->handle($attempt);
        }

        if (! $attempt->fresh()->isInProgress()) {
            $this->redirectRoute('quizzes.review', ['course' => $course, 'quiz' => $quiz, 'attempt' => $attempt]);
        }
    }

    /**
     * @return array{ok: bool, closed?: bool, remaining?: int|null}
     */
    #[Renderless]
    public function saveAnswer(int $questionId, mixed $value, SaveAttemptAnswer $save, FinishAttempt $finish): array
    {
        Gate::authorize('answer', $this->attempt);

        try {
            return ['ok' => true, 'remaining' => $save->handle($this->attempt, $questionId, $value)];
        } catch (AttemptClosedException) {
            $finish->handle($this->attempt);

            return ['ok' => false, 'closed' => true];
        }
    }

    #[Renderless]
    public function toggleFlag(int $questionId, bool $flagged, SaveAttemptAnswer $save): bool
    {
        Gate::authorize('answer', $this->attempt);

        try {
            $save->flag($this->attempt, $questionId, $flagged);

            return true;
        } catch (AttemptClosedException) {
            return false;
        }
    }

    public function finish(FinishAttempt $finish): void
    {
        Gate::authorize('answer', $this->attempt);

        $finish->handle($this->attempt);

        $this->redirectRoute('quizzes.review', ['course' => $this->course, 'quiz' => $this->quiz, 'attempt' => $this->attempt]);
    }

    public function render(): View
    {
        $attempt = $this->attempt->fresh();
        $questions = Question::query()->where('quiz_id', $this->quiz->id)->whereIn('id', $attempt->question_order)->get()->keyBy('id');
        $optionOrder = $attempt->option_order ?? [];

        // Hanya data tampilan: tanpa answer_key. Pilihan memakai urutan acak milik percobaan ini.
        $items = collect($attempt->question_order)
            ->map(fn ($id) => $questions->get((int) $id))
            ->filter()->values()
            ->map(fn (Question $q) => [
                'id' => $q->id,
                'type' => $q->type->value,
                'body' => $q->body,
                'points' => $q->points,
                'image' => $q->hasImage() ? route('questions.image', $q) : null,
                'options' => $q->type->value === 'multiple_choice' ? $q->optionsFor($optionOrder[(string) $q->id] ?? null) : [],
            ]);

        return view('livewire.quizzes.attempt', [
            'items' => $items,
            'answers' => (object) ($attempt->answers ?? []),
            'flags' => array_map('intval', $attempt->flags ?? []),
            'remaining' => $attempt->remainingSeconds(),
            'attemptNo' => $attempt->attempt_no,
        ])->title($this->quiz->title);
    }
}
