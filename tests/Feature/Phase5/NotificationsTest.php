<?php

use App\Actions\Assignments\GradeSubmission;
use App\Actions\Assignments\SaveAssignment;
use App\Actions\Quizzes\FinishAttempt;
use App\Actions\Quizzes\GradeEssays;
use App\Actions\Quizzes\SaveQuiz;
use App\Actions\Quizzes\StartAttempt;
use App\Enums\QuestionType;
use App\Enums\Role;
use App\Enums\SubmissionStatus;
use App\Livewire\Notifications\Index;
use App\Livewire\Quizzes\Show;
use App\Models\Assignment;
use App\Models\Submission;
use Illuminate\Console\Scheduling\Schedule;
use Livewire\Livewire;

beforeEach(function () {
    $this->teacher = makeUser(Role::Teacher);
    $this->course = makeCourse($this->teacher);
    $this->section = makeSection($this->course);
    $this->student = makeUser(Role::Student);
    $this->other = makeUser(Role::Student);
    enroll($this->course, $this->student);
    $this->outsider = makeUser(Role::Student);
});

function newAssignment(object $t, array $overrides = []): Assignment
{
    return app(SaveAssignment::class)->handle($t->course, $overrides + [
        'section_id' => $t->section->id, 'grade_category_id' => null, 'title' => 'Latihan Baru', 'instructions' => '<p>x</p>',
        'due_at' => now()->addDays(3), 'max_score' => 100, 'allow_late' => false,
    ]);
}

it('notifies enrolled students when an assignment is created', function () {
    $assignment = newAssignment($this);

    $note = $this->student->notifications()->first();

    expect($this->student->notifications)->toHaveCount(1)
        ->and($note->data['kind'])->toBe('assignment')
        ->and($note->data['title'])->toContain('Latihan Baru')
        ->and($note->data['url'])->toBe(route('assignments.show', ['course' => $this->course, 'assignment' => $assignment], false))
        ->and($this->teacher->notifications)->toHaveCount(0)
        ->and($this->outsider->notifications)->toHaveCount(0);
});

it('does not notify again when an assignment is edited', function () {
    $assignment = newAssignment($this);

    app(SaveAssignment::class)->handle($this->course, [
        'section_id' => $this->section->id, 'grade_category_id' => null, 'title' => 'Judul Baru', 'instructions' => '',
        'due_at' => now()->addDays(4), 'max_score' => 100, 'allow_late' => false,
    ], $assignment);

    expect($this->student->notifications)->toHaveCount(1);
});

it('notifies a student when a submission is graded', function () {
    $assignment = newAssignment($this);
    $this->student->notifications()->delete();

    app(GradeSubmission::class)->handle($assignment, $this->student, 87.5, 'Bagus', $this->teacher);

    $note = $this->student->notifications()->first();

    expect($note->data['kind'])->toBe('grade')->and($note->data['body'])->toBe('Nilai 87,5 dari 100');
});

function quizData(object $t, array $overrides = []): array
{
    return $overrides + [
        'section_id' => $t->section->id, 'grade_category_id' => null, 'title' => 'Kuis Baru', 'instructions' => '', 'is_published' => true,
        'opens_at' => null, 'closes_at' => now()->addDays(2), 'duration_minutes' => null, 'shuffle_questions' => false,
        'shuffle_options' => false, 'max_attempts' => 1, 'show_result_at' => null,
    ];
}

it('notifies students once when a quiz is first published', function () {
    $quiz = app(SaveQuiz::class)->handle($this->course, quizData($this, ['is_published' => false]));
    expect($this->student->notifications)->toHaveCount(0);

    Livewire::actingAs($this->teacher)->test(Show::class, ['course' => $this->course, 'quiz' => $quiz])->call('togglePublish');
    expect($this->student->notifications()->count())->toBe(1);

    // Ditarik lalu diterbitkan ulang: tidak ada notifikasi kedua.
    Livewire::actingAs($this->teacher)->test(Show::class, ['course' => $this->course, 'quiz' => $quiz->fresh()])->call('togglePublish');
    Livewire::actingAs($this->teacher)->test(Show::class, ['course' => $this->course, 'quiz' => $quiz->fresh()])->call('togglePublish');
    app(SaveQuiz::class)->handle($this->course, quizData($this), $quiz->fresh());

    expect($this->student->notifications()->count())->toBe(1)->and($this->student->notifications()->first()->data['kind'])->toBe('quiz');
});

it('notifies when a quiz is created already published', function () {
    app(SaveQuiz::class)->handle($this->course, quizData($this));

    expect($this->student->notifications()->count())->toBe(1);
});

it('notifies the student when an essay quiz becomes fully graded, but not for auto-graded ones', function () {
    $quiz = makeQuiz($this->course, ['max_attempts' => 2]);
    $tf = addQuestion($quiz, QuestionType::TrueFalse);
    $essay = addQuestion($quiz, QuestionType::Essay, ['points' => 5]);

    $attempt = app(StartAttempt::class)->handle($quiz->fresh(), $this->student);
    $attempt = app(FinishAttempt::class)->handle($attempt);
    expect($this->student->notifications()->count())->toBe(0);

    app(GradeEssays::class)->handle($attempt, [$essay->id => 4.0]);
    app(GradeEssays::class)->handle($attempt->fresh(), [$essay->id => 5.0]); // koreksi nilai: tidak mengirim ulang

    expect($this->student->notifications()->count())->toBe(1)
        ->and($this->student->notifications()->first()->data['kind'])->toBe('grade');

    $essay->delete();
    $auto = app(StartAttempt::class)->handle($quiz->fresh(), $this->student);
    app(FinishAttempt::class)->handle($auto);

    expect($this->student->notifications()->count())->toBe(1);
});

it('sends H-1 reminders only to students who have not submitted, once', function () {
    $done = makeUser(Role::Student);
    $late = makeUser(Role::Student);
    enroll($this->course, $done);
    enroll($this->course, $late);

    $soon = newAssignment($this, ['title' => 'Besok', 'due_at' => now()->addHours(10)]);
    newAssignment($this, ['title' => 'Minggu Depan', 'due_at' => now()->addDays(7)]);
    newAssignment($this, ['title' => 'Sudah Lewat', 'due_at' => now()->subHour()]);
    Submission::create(['assignment_id' => $soon->id, 'user_id' => $done->id, 'body' => 'x', 'status' => SubmissionStatus::Submitted, 'submitted_at' => now()]);

    foreach ([$this->student, $done, $late] as $u) {
        $u->notifications()->delete();
    }

    $this->artisan('reminders:due')->assertSuccessful();

    $titles = fn ($u) => $u->notifications()->get()->pluck('data.title')->all();

    expect($titles($this->student))->toHaveCount(1)->and($titles($this->student)[0])->toContain('Besok')
        ->and($titles($late))->toHaveCount(1)
        ->and($titles($done))->toBe([])
        ->and($titles($this->outsider))->toBe([]);

    $this->artisan('reminders:due')->assertSuccessful();
    expect($this->student->notifications()->count())->toBe(1);
});

it('reminds only students who have not tried a quiz closing within 24 hours', function () {
    $tried = makeUser(Role::Student);
    enroll($this->course, $tried);
    $quiz = makeQuiz($this->course, ['closes_at' => now()->addHours(5), 'title' => 'Kuis Besok']);
    addQuestion($quiz);
    app(StartAttempt::class)->handle($quiz->fresh(), $tried);

    foreach ([$this->student, $tried] as $u) {
        $u->notifications()->delete();
    }

    $this->artisan('reminders:due')->assertSuccessful();

    expect($this->student->notifications()->count())->toBe(1)
        ->and($this->student->notifications()->first()->data['title'])->toContain('Kuis Besok')
        ->and($tried->notifications()->count())->toBe(0)
        ->and($quiz->fresh()->due_reminded_at)->not->toBeNull();
});

it('registers the reminders on the scheduler hourly', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'reminders:due'));

    expect($event)->not->toBeNull()->and($event->expression)->toBe('0 * * * *');
});

it('lists only the user\'s own notifications and shows the unread badge', function () {
    newAssignment($this);
    newAssignment($this, ['title' => 'Kedua']);

    $this->actingAs($this->student)->get(route('notifications.index'))->assertOk()->assertSee('Latihan Baru')->assertSee('Kedua');
    $this->actingAs($this->outsider)->get(route('notifications.index'))->assertOk()->assertDontSee('Latihan Baru')->assertSee('Belum ada notifikasi');
    $this->actingAs($this->student)->get(route('home'))->assertSee('2 belum dibaca');
});

it('marks a notification read on open and redirects to its target', function () {
    $assignment = newAssignment($this);
    $note = $this->student->notifications()->first();

    Livewire::actingAs($this->student)->test(Index::class)->call('open', $note->id)
        ->assertRedirect(route('assignments.show', ['course' => $this->course, 'assignment' => $assignment]));

    expect($note->fresh()->read_at)->not->toBeNull();
});

it('does not let a user open or read someone else\'s notification', function () {
    newAssignment($this);
    $note = $this->student->notifications()->first();

    Livewire::actingAs($this->outsider)->test(Index::class)->call('open', $note->id)->assertNotFound();
    Livewire::actingAs($this->outsider)->test(Index::class)->call('markRead', $note->id)->assertNotFound();

    expect($note->fresh()->read_at)->toBeNull();
});

it('marks all as read and filters unread', function () {
    newAssignment($this);
    newAssignment($this, ['title' => 'Kedua']);
    $first = $this->student->notifications()->first();
    $first->markAsRead();

    Livewire::actingAs($this->student)->test(Index::class)->set('filter', 'unread')->assertViewHas('notifications', fn ($p) => $p->count() === 1)
        ->call('markAllRead')->assertViewHas('unread', 0);

    expect($this->student->unreadNotifications()->count())->toBe(0);
});

it('requires login for notifications', function () {
    $this->get(route('notifications.index'))->assertRedirect(route('login'));
});
