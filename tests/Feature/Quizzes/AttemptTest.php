<?php

use App\Actions\Quizzes\AttemptClosedException;
use App\Actions\Quizzes\FinishAttempt;
use App\Actions\Quizzes\SaveAttemptAnswer;
use App\Actions\Quizzes\StartAttempt;
use App\Enums\AttemptStatus;
use App\Enums\QuestionType;
use App\Enums\Role;
use App\Livewire\Quizzes\Attempt;
use App\Livewire\Quizzes\Show;
use App\Models\QuizAttempt;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function () {
    $this->teacher = makeUser(Role::Teacher);
    $this->course = makeCourse($this->teacher);
    $this->student = makeUser(Role::Student);
    enroll($this->course, $this->student);
    $this->quiz = makeQuiz($this->course, ['duration_minutes' => 30, 'max_attempts' => 2]);
    $this->mc = addQuestion($this->quiz);
    $this->tf = addQuestion($this->quiz, QuestionType::TrueFalse);
    $this->short = addQuestion($this->quiz, QuestionType::ShortAnswer);
    $this->essay = addQuestion($this->quiz, QuestionType::Essay, ['points' => 5]);
});

function startAttempt(object $test, ?object $student = null): QuizAttempt
{
    return app(StartAttempt::class)->handle($test->quiz->fresh(), $student ?? $test->student);
}

it('starts an attempt with a server deadline of start + duration', function () {
    $attempt = startAttempt($this);

    expect($attempt->status)->toBe(AttemptStatus::InProgress)
        ->and($attempt->attempt_no)->toBe(1)
        ->and($attempt->question_order)->toHaveCount(4)
        ->and($attempt->max_score)->toBe(11.0)
        ->and($attempt->started_at->diffInMinutes($attempt->deadline_at))->toBe(30.0)
        ->and($attempt->remainingSeconds())->toBeBetween(1790, 1800);
});

it('caps the deadline at the closing time', function () {
    $this->quiz->update(['closes_at' => now()->addMinutes(5)]);

    expect(startAttempt($this)->remainingSeconds())->toBeLessThanOrEqual(300);
});

it('has no deadline without duration and closing time', function () {
    $this->quiz->update(['duration_minutes' => null]);

    $attempt = startAttempt($this);

    expect($attempt->deadline_at)->toBeNull()->and($attempt->remainingSeconds())->toBeNull()->and($attempt->isExpired())->toBeFalse();
});

it('resumes the running attempt instead of creating a new one', function () {
    $first = startAttempt($this);

    expect(startAttempt($this)->id)->toBe($first->id)->and(QuizAttempt::count())->toBe(1);
});

it('refuses to start outside the allowed conditions', function (string $message, Closure $setup) {
    $setup($this);

    expect(fn () => startAttempt($this))->toThrow(ValidationException::class, $message);
})->with([
    'draft' => ['belum diterbitkan', fn ($t) => $t->quiz->update(['is_published' => false])],
    'not opened' => ['belum dibuka', fn ($t) => $t->quiz->update(['opens_at' => now()->addHour()])],
    'closed' => ['sudah ditutup', fn ($t) => $t->quiz->update(['closes_at' => now()->subMinute()])],
    'no questions' => ['belum memiliki soal', fn ($t) => $t->quiz->questions()->delete()],
]);

it('limits the number of attempts', function () {
    foreach ([1, 2] as $n) {
        $attempt = startAttempt($this);
        app(FinishAttempt::class)->handle($attempt);
        expect($attempt->fresh()->attempt_no)->toBe($n);
    }

    expect(fn () => startAttempt($this))->toThrow(ValidationException::class, 'habis');
});

it('stores one shuffled order per attempt and keeps it on reload', function () {
    $this->quiz->update(['shuffle_questions' => true, 'shuffle_options' => true]);

    $attempt = startAttempt($this);
    $ids = $this->quiz->questions()->pluck('id')->all();

    expect($attempt->question_order)->toHaveCount(4)->and(collect($attempt->question_order)->sort()->values()->all())->toBe(collect($ids)->sort()->values()->all())
        ->and($attempt->option_order)->toHaveKey((string) $this->mc->id);

    $again = QuizAttempt::find($attempt->id);
    expect($again->question_order)->toBe($attempt->question_order)->and($again->option_order)->toBe($attempt->option_order);
});

it('saves answers per question type and normalizes them', function () {
    $attempt = startAttempt($this);
    $save = app(SaveAttemptAnswer::class);
    $correct = $this->mc->answer_key['correct'];

    $save->handle($attempt, $this->mc->id, $correct);
    $save->handle($attempt, $this->tf->id, 'true');
    $save->handle($attempt, $this->short->id, '  jakarta  ');
    $save->handle($attempt, $this->essay->id, 'Jawaban esai');

    expect($attempt->fresh()->answers)->toBe([
        (string) $this->mc->id => $correct,
        (string) $this->tf->id => 'true',
        (string) $this->short->id => 'jakarta',
        (string) $this->essay->id => 'Jawaban esai',
    ]);
});

it('ignores invalid values and clears an answer when it is emptied', function () {
    $attempt = startAttempt($this);
    $save = app(SaveAttemptAnswer::class);

    $save->handle($attempt, $this->mc->id, 'bukan-id-pilihan');
    $save->handle($attempt, $this->tf->id, 'mungkin');
    $save->handle($attempt, $this->short->id, 'ok');
    $save->handle($attempt, $this->short->id, '   ');

    expect($attempt->fresh()->answers)->toBe([]);
});

it('rejects answers for questions outside the attempt', function () {
    $attempt = startAttempt($this);
    $foreign = addQuestion(makeQuiz($this->course, ['title' => 'Lain', 'position' => 2]));

    expect(fn () => app(SaveAttemptAnswer::class)->handle($attempt, $foreign->id, 'x'))->toThrow(NotFoundHttpException::class);
});

it('accepts a late answer inside the grace window and rejects it after', function () {
    $attempt = startAttempt($this);
    $attempt->update(['deadline_at' => now()->subSeconds(QuizAttempt::GRACE_SECONDS - 3)]);

    app(SaveAttemptAnswer::class)->handle($attempt->fresh(), $this->tf->id, 'true');
    expect($attempt->fresh()->answers)->toHaveKey((string) $this->tf->id);

    $attempt->update(['deadline_at' => now()->subSeconds(QuizAttempt::GRACE_SECONDS + 5)]);

    expect(fn () => app(SaveAttemptAnswer::class)->handle($attempt->fresh(), $this->short->id, 'telat'))->toThrow(AttemptClosedException::class);
});

it('refuses answers once the attempt is finished', function () {
    $attempt = startAttempt($this);
    app(FinishAttempt::class)->handle($attempt);

    expect(fn () => app(SaveAttemptAnswer::class)->handle($attempt->fresh(), $this->tf->id, 'true'))->toThrow(AttemptClosedException::class);
});

it('toggles the doubt flag without duplicates', function () {
    $attempt = startAttempt($this);
    $save = app(SaveAttemptAnswer::class);

    $save->flag($attempt, $this->mc->id, true);
    $save->flag($attempt, $this->mc->id, true);
    $save->flag($attempt, $this->tf->id, true);
    expect($attempt->fresh()->flags)->toBe([$this->mc->id, $this->tf->id]);

    $save->flag($attempt, $this->mc->id, false);
    expect($attempt->fresh()->flags)->toBe([$this->tf->id]);
});

it('autosaves through the attempt component and finishes with a redirect', function () {
    $attempt = startAttempt($this);
    $component = Livewire::actingAs($this->student)->test(Attempt::class, ['course' => $this->course, 'quiz' => $this->quiz, 'attempt' => $attempt]);

    $component->call('saveAnswer', $this->tf->id, 'false')->assertReturned(fn ($r) => $r['ok'] === true && $r['remaining'] > 0);
    $component->call('toggleFlag', $this->tf->id, true)->assertReturned(true);

    expect($attempt->fresh()->answers[(string) $this->tf->id])->toBe('false')->and($attempt->fresh()->flags)->toBe([$this->tf->id]);

    $component->call('finish')->assertRedirect(route('quizzes.review', ['course' => $this->course, 'quiz' => $this->quiz, 'attempt' => $attempt]));
    expect($attempt->fresh()->isInProgress())->toBeFalse();
});

it('closes the attempt when an answer arrives after the deadline', function () {
    $attempt = startAttempt($this);
    $component = Livewire::actingAs($this->student)->test(Attempt::class, ['course' => $this->course, 'quiz' => $this->quiz, 'attempt' => $attempt]);

    $attempt->update(['deadline_at' => now()->subMinute()]);

    $component->call('saveAnswer', $this->short->id, 'terlambat')->assertReturned(['ok' => false, 'closed' => true]);

    expect($attempt->fresh()->status)->not->toBe(AttemptStatus::InProgress)->and($attempt->fresh()->answers)->toBe([]);
});

it('finishes an expired attempt when the page is opened and redirects to the review', function () {
    $attempt = startAttempt($this);
    $attempt->update(['deadline_at' => now()->subMinutes(2)]);

    Livewire::actingAs($this->student)->test(Attempt::class, ['course' => $this->course, 'quiz' => $this->quiz, 'attempt' => $attempt])
        ->assertRedirect(route('quizzes.review', ['course' => $this->course, 'quiz' => $this->quiz, 'attempt' => $attempt]));

    expect($attempt->fresh()->isInProgress())->toBeFalse();
});

it('never sends the answer key to the student page', function () {
    $this->mc->update(['body' => '<p>Soal pilihan</p>']);
    $this->short->update(['body' => '<p>Soal isian</p>']);
    $attempt = startAttempt($this);

    $html = Livewire::actingAs($this->student)->test(Attempt::class, ['course' => $this->course, 'quiz' => $this->quiz, 'attempt' => $attempt])->html();

    expect($html)->toContain('Soal pilihan')
        ->not->toContain('jakarta')
        ->not->toContain('Rubrik rahasia')
        ->not->toContain('answer_key')
        ->not->toContain('"correct"');
});

it('lets only the owner open or answer an attempt', function () {
    $attempt = startAttempt($this);
    $classmate = makeUser(Role::Student);
    enroll($this->course, $classmate);

    foreach ([$classmate, $this->teacher] as $user) {
        $this->actingAs($user)->get(route('quizzes.attempt', ['course' => $this->course, 'quiz' => $this->quiz, 'attempt' => $attempt]))->assertForbidden();
    }

    $this->actingAs($this->student)->get(route('quizzes.attempt', ['course' => $this->course, 'quiz' => $this->quiz, 'attempt' => $attempt]))->assertOk();
});

it('does not let teachers, outsiders or unenrolled students start a quiz', function () {
    $outsider = makeUser(Role::Student);

    Livewire::actingAs($this->teacher)->test(Show::class, ['course' => $this->course, 'quiz' => $this->quiz])->call('start')->assertForbidden();
    Livewire::actingAs($outsider)->test(Show::class, ['course' => $this->course, 'quiz' => $this->quiz])->assertForbidden();
});

it('starts and redirects to the attempt from the show page', function () {
    Livewire::actingAs($this->student)->test(Show::class, ['course' => $this->course, 'quiz' => $this->quiz])
        ->call('start')
        ->assertRedirect(route('quizzes.attempt', ['course' => $this->course, 'quiz' => $this->quiz, 'attempt' => QuizAttempt::first()]));

    expect(QuizAttempt::count())->toBe(1);
});

it('shows the error instead of crashing when the quiz is closed', function () {
    $this->quiz->update(['closes_at' => now()->subMinute()]);

    Livewire::actingAs($this->student)->test(Show::class, ['course' => $this->course, 'quiz' => $this->quiz])
        ->call('start')->assertHasErrors(['quiz']);
});

it('finalizes expired attempts from the scheduler command', function () {
    $expired = startAttempt($this);
    $expired->update(['deadline_at' => now()->subMinutes(5)]);

    $other = makeUser(Role::Student);
    enroll($this->course, $other);
    $running = startAttempt($this, $other);

    $this->artisan('quizzes:finalize-expired')->assertSuccessful();

    expect($expired->fresh()->isInProgress())->toBeFalse()
        ->and($expired->fresh()->finished_at->lessThanOrEqualTo($expired->deadline_at))->toBeTrue()
        ->and($running->fresh()->isInProgress())->toBeTrue();
});

it('registers the finalize command on the scheduler every minute', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'quizzes:finalize-expired'));

    expect($event)->not->toBeNull()->and($event->expression)->toBe('* * * * *');
});
