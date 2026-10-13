<?php

use App\Actions\Quizzes\FinishAttempt;
use App\Actions\Quizzes\GradeEssays;
use App\Actions\Quizzes\ManageQuestions;
use App\Actions\Quizzes\SaveAttemptAnswer;
use App\Actions\Quizzes\StartAttempt;
use App\Enums\AttemptStatus;
use App\Enums\QuestionType;
use App\Enums\Role;
use App\Livewire\Quizzes\Results;
use App\Livewire\Quizzes\Review;
use App\Models\AcademicTerm;
use App\Models\QuizAttempt;
use App\Support\QuizGrader;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->teacher = makeUser(Role::Teacher);
    $this->course = makeCourse($this->teacher);
    $this->student = makeUser(Role::Student);
    enroll($this->course, $this->student);
    $this->quiz = makeQuiz($this->course, ['max_attempts' => 3]);
    $this->mc = addQuestion($this->quiz, data: ['points' => 2]);
    $this->tf = addQuestion($this->quiz, QuestionType::TrueFalse, ['points' => 1]);
    $this->short = addQuestion($this->quiz, QuestionType::ShortAnswer, ['points' => 2, 'accepted' => ['Jakarta', 'DKI Jakarta']]);
});

/** Mengerjakan dan menyelesaikan satu percobaan dengan jawaban yang diberikan (kunci: nama soal). */
function takeQuiz(object $t, array $answers, ?object $student = null): QuizAttempt
{
    $attempt = app(StartAttempt::class)->handle($t->quiz->fresh(), $student ?? $t->student);
    $save = app(SaveAttemptAnswer::class);

    foreach ($answers as $name => $value) {
        $save->handle($attempt, $t->{$name}->id, $value);
    }

    return app(FinishAttempt::class)->handle($attempt);
}

it('grades objective questions automatically', function () {
    $attempt = takeQuiz($this, ['mc' => $this->mc->answer_key['correct'], 'tf' => 'true', 'short' => 'jakarta']);

    expect($attempt->status)->toBe(AttemptStatus::Graded)->and($attempt->score)->toBe(5.0)->and($attempt->max_score)->toBe(5.0);
});

it('gives zero for wrong or missing answers', function () {
    $wrong = collect($this->mc->options)->firstWhere('id', '!=', $this->mc->answer_key['correct'])['id'];

    expect(takeQuiz($this, ['mc' => $wrong, 'tf' => 'false'])->score)->toBe(0.0);
});

it('matches short answers ignoring case, spacing and punctuation unless case sensitive', function () {
    expect(takeQuiz($this, ['short' => '  dki   JAKARTA '])->auto_score)->toBe(2.0);

    $this->short->update(['answer_key' => ['accepted' => ['Jakarta'], 'case_sensitive' => true]]);

    expect(takeQuiz($this, ['short' => 'jakarta'])->auto_score)->toBe(0.0)
        ->and(takeQuiz($this, ['short' => 'Jakarta'])->auto_score)->toBe(2.0);
});

it('keeps an attempt with essays as submitted until the teacher grades them', function () {
    $essay = addQuestion($this->quiz, QuestionType::Essay, ['points' => 5]);
    $this->essay = $essay;

    $attempt = takeQuiz($this, ['mc' => $this->mc->answer_key['correct'], 'tf' => 'true', 'essay' => 'Esai saya']);

    expect($attempt->status)->toBe(AttemptStatus::Submitted)->and($attempt->score)->toBeNull()->and($attempt->auto_score)->toBe(3.0);

    $graded = app(GradeEssays::class)->handle($attempt, [$essay->id => 4.5], [$essay->id => 'Bagus']);

    expect($graded->status)->toBe(AttemptStatus::Graded)->and($graded->score)->toBe(7.5)
        ->and($graded->manual_scores[(string) $essay->id])->toBe(['score' => 4.5, 'feedback' => 'Bagus']);
});

it('validates essay scores and keeps the attempt pending when one is cleared', function () {
    $essay = addQuestion($this->quiz, QuestionType::Essay, ['points' => 5]);
    $attempt = takeQuiz($this, ['tf' => 'true']);

    expect(fn () => app(GradeEssays::class)->handle($attempt, [$essay->id => 6.0]))->toThrow(ValidationException::class)
        ->and(fn () => app(GradeEssays::class)->handle($attempt, [$essay->id => -1.0]))->toThrow(ValidationException::class);

    app(GradeEssays::class)->handle($attempt, [$essay->id => 3.0]);
    $cleared = app(GradeEssays::class)->handle($attempt->fresh(), [$essay->id => null]);

    expect($cleared->status)->toBe(AttemptStatus::Submitted)->and($cleared->score)->toBeNull();
});

it('blocks essay grading when the term is locked', function () {
    $essay = addQuestion($this->quiz, QuestionType::Essay, ['points' => 5]);
    $attempt = takeQuiz($this, ['tf' => 'true']);

    AcademicTerm::query()->update(['is_locked' => true]);

    expect(fn () => app(GradeEssays::class)->handle($attempt->fresh(), [$essay->id => 3.0]))->toThrow(AuthorizationException::class);

    Livewire::actingAs($this->teacher)->test(Review::class, ['course' => $this->course, 'quiz' => $this->quiz, 'attempt' => $attempt])
        ->set("scores.{$essay->id}", '3')->call('saveGrades')->assertForbidden();

    expect($attempt->fresh()->status)->toBe(AttemptStatus::Submitted);
});

it('grades essays from the review page', function () {
    $essay = addQuestion($this->quiz, QuestionType::Essay, ['points' => 5]);
    $attempt = takeQuiz($this, ['tf' => 'true', 'short' => 'Jakarta']);

    Livewire::actingAs($this->teacher)->test(Review::class, ['course' => $this->course, 'quiz' => $this->quiz, 'attempt' => $attempt])
        ->set("scores.{$essay->id}", '4')->set("notes.{$essay->id}", 'Cukup')
        ->call('saveGrades')->assertHasNoErrors();

    expect($attempt->fresh()->status)->toBe(AttemptStatus::Graded)->and($attempt->fresh()->score)->toBe(7.0);
});

it('rejects a non numeric essay score on the review page', function () {
    $essay = addQuestion($this->quiz, QuestionType::Essay, ['points' => 5]);
    $attempt = takeQuiz($this, ['tf' => 'true']);

    Livewire::actingAs($this->teacher)->test(Review::class, ['course' => $this->course, 'quiz' => $this->quiz, 'attempt' => $attempt])
        ->set("scores.{$essay->id}", 'abc')->call('saveGrades')->assertHasErrors(["scores.{$essay->id}"]);
});

it('does not let a student or the principal grade essays', function () {
    $essay = addQuestion($this->quiz, QuestionType::Essay, ['points' => 5]);
    $attempt = takeQuiz($this, ['tf' => 'true']);
    $principal = makeUser(Role::Principal);

    foreach ([$this->student, $principal] as $user) {
        Livewire::actingAs($user)->test(Review::class, ['course' => $this->course, 'quiz' => $this->quiz, 'attempt' => $attempt])
            ->set("scores.{$essay->id}", '5')->call('saveGrades')->assertForbidden();
    }

    expect($attempt->fresh()->status)->toBe(AttemptStatus::Submitted);
});

it('recomputes scores after the answer key is corrected', function () {
    $attempt = takeQuiz($this, ['tf' => 'true']);
    expect($attempt->score)->toBe(1.0);

    $this->tf->update(['answer_key' => ['correct' => false]]);

    Livewire::actingAs($this->teacher)->test(Results::class, ['course' => $this->course, 'quiz' => $this->quiz])->call('recompute');

    expect($attempt->fresh()->score)->toBe(0.0);
});

it('computes the per question breakdown in the grader', function () {
    $attempt = takeQuiz($this, ['mc' => $this->mc->answer_key['correct'], 'tf' => 'false']);
    $result = QuizGrader::grade($this->quiz->questions()->get(), $attempt->fresh());

    expect($result['perQuestion'][$this->mc->id]['correct'])->toBeTrue()
        ->and($result['perQuestion'][$this->tf->id]['correct'])->toBeFalse()
        ->and($result['perQuestion'][$this->short->id]['answered'])->toBeFalse();
});

it('shows students only their own attempts', function () {
    $mine = takeQuiz($this, ['tf' => 'true']);
    $classmate = makeUser(Role::Student);
    enroll($this->course, $classmate);
    $theirs = takeQuiz($this, ['tf' => 'false'], $classmate);

    $route = fn ($a) => route('quizzes.review', ['course' => $this->course, 'quiz' => $this->quiz, 'attempt' => $a]);

    $this->actingAs($this->student)->get($route($mine))->assertOk();
    $this->actingAs($this->student)->get($route($theirs))->assertForbidden();
    $this->actingAs($this->teacher)->get($route($theirs))->assertOk();
    $this->actingAs(makeUser(Role::Principal))->get($route($theirs))->assertOk();
});

it('hides the score and answers until the result time', function () {
    $this->quiz->update(['show_result_at' => now()->addDay()]);
    $attempt = takeQuiz($this, ['tf' => 'true']);

    Livewire::actingAs($this->student)->test(Review::class, ['course' => $this->course, 'quiz' => $this->quiz, 'attempt' => $attempt])
        ->assertSee('Hasil ditampilkan mulai')->assertDontSee('Jawabanmu');

    $this->quiz->update(['show_result_at' => now()->subMinute()]);

    Livewire::actingAs($this->student)->test(Review::class, ['course' => $this->course, 'quiz' => $this->quiz->fresh(), 'attempt' => $attempt])
        ->assertSee('Jawabanmu');
});

it('reveals the answer key only after the quiz closed or attempts are used up', function () {
    $attempt = takeQuiz($this, ['short' => 'salah']);
    $open = fn () => Livewire::actingAs($this->student)->test(Review::class, ['course' => $this->course, 'quiz' => $this->quiz->fresh(), 'attempt' => $attempt]);

    // Kuis masih dibuka dan masih ada percobaan: kunci tidak muncul.
    $open()->assertDontSee('Jawaban benar')->assertDontSee('DKI Jakarta');

    // Kuis ditutup: kunci terbuka.
    $this->quiz->update(['closes_at' => now()->subMinute()]);
    $open()->assertSee('Jawaban benar')->assertSee('DKI Jakarta');
});

it('reveals the answer key when all attempts are used', function () {
    $this->quiz->update(['max_attempts' => 1]);
    $attempt = takeQuiz($this, ['short' => 'salah']);

    Livewire::actingAs($this->student)->test(Review::class, ['course' => $this->course, 'quiz' => $this->quiz->fresh(), 'attempt' => $attempt])
        ->assertSee('DKI Jakarta');
});

it('shows the essay rubric to staff only', function () {
    addQuestion($this->quiz, QuestionType::Essay, ['points' => 5]);
    $attempt = takeQuiz($this, []);
    $this->quiz->update(['closes_at' => now()->subMinute()]);

    Livewire::actingAs($this->teacher)->test(Review::class, ['course' => $this->course, 'quiz' => $this->quiz->fresh(), 'attempt' => $attempt])->assertSee('Rubrik rahasia');
    Livewire::actingAs($this->student)->test(Review::class, ['course' => $this->course, 'quiz' => $this->quiz->fresh(), 'attempt' => $attempt])->assertDontSee('Rubrik rahasia');
});

it('redirects the owner of a running attempt to the answer page', function () {
    $attempt = app(StartAttempt::class)->handle($this->quiz->fresh(), $this->student);

    Livewire::actingAs($this->student)->test(Review::class, ['course' => $this->course, 'quiz' => $this->quiz, 'attempt' => $attempt])
        ->assertRedirect(route('quizzes.attempt', ['course' => $this->course, 'quiz' => $this->quiz, 'attempt' => $attempt]));

    Livewire::actingAs($this->teacher)->test(Review::class, ['course' => $this->course, 'quiz' => $this->quiz, 'attempt' => $attempt])->assertForbidden();
});

it('lists results for staff and filters those needing grading', function () {
    addQuestion($this->quiz, QuestionType::Essay, ['points' => 5]);
    takeQuiz($this, ['tf' => 'true']);

    $other = makeUser(Role::Student, ['name' => 'Budi Santoso']);
    enroll($this->course, $other);
    $quiz = $this->quiz;
    $quiz->questions()->where('type', QuestionType::Essay->value)->delete();
    takeQuiz($this, ['tf' => 'true'], $other);

    Livewire::actingAs($this->teacher)->test(Results::class, ['course' => $this->course, 'quiz' => $this->quiz])
        ->assertSee($this->student->name)->assertSee('Budi Santoso')
        ->set('filter', 'needs')->assertSee($this->student->name)->assertDontSee('Budi Santoso');

    Livewire::actingAs($this->teacher)->test(Results::class, ['course' => $this->course, 'quiz' => $this->quiz])
        ->set('search', 'Budi')->assertSee('Budi Santoso')->assertDontSee($this->student->name);
});

it('lets students not see the results page', function () {
    Livewire::actingAs($this->student)->test(Results::class, ['course' => $this->course, 'quiz' => $this->quiz])->assertForbidden();
    Livewire::actingAs(makeUser(Role::Principal))->test(Results::class, ['course' => $this->course, 'quiz' => $this->quiz])->assertOk();
});

it('serves question images only to staff and to students who started the quiz', function () {
    Storage::fake(config('courses.disk'));
    $question = app(ManageQuestions::class)->save($this->quiz, [
        'type' => QuestionType::TrueFalse, 'body' => '<p>Gambar</p>', 'points' => 1, 'true_false' => true,
    ], UploadedFile::fake()->image('soal.png'));

    $url = route('questions.image', $question);

    $this->actingAs($this->teacher)->get($url)->assertOk();
    $this->actingAs($this->student)->get($url)->assertForbidden();

    app(StartAttempt::class)->handle($this->quiz->fresh(), $this->student);
    $this->actingAs($this->student)->get($url)->assertOk();

    $outsider = makeUser(Role::Student);
    $this->actingAs($outsider)->get($url)->assertForbidden();
    $this->actingAs(makeUser(Role::Principal))->get($url)->assertOk();
});

it('returns 404 for a question without an image and requires login', function () {
    $this->actingAs($this->teacher)->get(route('questions.image', $this->mc))->assertNotFound();

    auth()->logout();
    $this->get(route('questions.image', $this->mc))->assertRedirect();
});
