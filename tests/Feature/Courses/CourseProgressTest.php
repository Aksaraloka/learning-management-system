<?php

use App\Actions\Quizzes\FinishAttempt;
use App\Actions\Quizzes\StartAttempt;
use App\Enums\QuestionType;
use App\Enums\Role;
use App\Enums\SubmissionStatus;
use App\Livewire\Courses\Index;
use App\Models\Submission;
use App\Support\CourseProgress;
use Livewire\Livewire;

beforeEach(function () {
    $this->teacher = makeUser(Role::Teacher);
    $this->course = makeCourse($this->teacher, ['name' => 'Algoritma']);
    $this->ani = makeUser(Role::Student);
    enroll($this->course, $this->ani);
});

it('computes progress as submitted assignments plus attempted quizzes over published items', function () {
    $a1 = makeAssignment($this->course, ['title' => 'T1']);
    $a2 = makeAssignment($this->course, ['title' => 'T2', 'position' => 2]);
    $quiz = makeQuiz($this->course, ['title' => 'K1']);
    addQuestion($quiz, QuestionType::TrueFalse);
    makeQuiz($this->course, ['title' => 'Draf', 'is_published' => false, 'position' => 2]);

    expect(CourseProgress::forStudent($this->ani, [$this->course->id])[$this->course->id])->toBe(['done' => 0, 'total' => 3, 'percent' => 0]);

    Submission::create(['assignment_id' => $a1->id, 'user_id' => $this->ani->id, 'body' => 'x', 'status' => SubmissionStatus::Submitted, 'submitted_at' => now()]);
    Submission::create(['assignment_id' => $a2->id, 'user_id' => $this->ani->id, 'body' => 'x', 'status' => SubmissionStatus::Returned]);

    expect(CourseProgress::forStudent($this->ani, [$this->course->id])[$this->course->id]['done'])->toBe(1);

    $attempt = app(StartAttempt::class)->handle($quiz->fresh(), $this->ani);
    expect(CourseProgress::forStudent($this->ani, [$this->course->id])[$this->course->id]['done'])->toBe(1); // masih berjalan

    app(FinishAttempt::class)->handle($attempt);

    expect(CourseProgress::forStudent($this->ani, [$this->course->id])[$this->course->id])->toBe(['done' => 2, 'total' => 3, 'percent' => 67]);
});

it('counts a quiz once however many attempts and ignores other students and deleted items', function () {
    $quiz = makeQuiz($this->course, ['max_attempts' => 3]);
    addQuestion($quiz, QuestionType::TrueFalse);
    $other = makeUser(Role::Student);
    enroll($this->course, $other);

    foreach ([1, 2] as $n) {
        app(FinishAttempt::class)->handle(app(StartAttempt::class)->handle($quiz->fresh(), $this->ani));
    }

    app(FinishAttempt::class)->handle(app(StartAttempt::class)->handle($quiz->fresh(), $other));
    $gone = makeAssignment($this->course, ['position' => 5]);
    Submission::create(['assignment_id' => $gone->id, 'user_id' => $this->ani->id, 'body' => 'x', 'status' => SubmissionStatus::Graded, 'score' => 5]);
    $gone->delete();

    expect(CourseProgress::forStudent($this->ani, [$this->course->id])[$this->course->id])->toBe(['done' => 1, 'total' => 1, 'percent' => 100]);
});

it('reports zero totals for a course without items and handles empty input', function () {
    expect(CourseProgress::forStudent($this->ani, [$this->course->id])[$this->course->id])->toBe(['done' => 0, 'total' => 0, 'percent' => 0])
        ->and(CourseProgress::forStudent($this->ani, []))->toBe([]);
});

it('shows the progress bar to students on the list, course page and dashboard, but not to teachers', function () {
    $a = makeAssignment($this->course, ['title' => 'T1']);
    makeAssignment($this->course, ['title' => 'T2', 'position' => 2]);
    Submission::create(['assignment_id' => $a->id, 'user_id' => $this->ani->id, 'body' => 'x', 'status' => SubmissionStatus::Submitted, 'submitted_at' => now()]);

    Livewire::actingAs($this->ani)->test(Index::class)->assertSee('50%')->assertSee('(1/2)');
    $this->actingAs($this->ani)->get(route('courses.show', $this->course))->assertOk()->assertSee('role="progressbar"', false)->assertSee('50%');
    $this->actingAs($this->ani)->get(route('home'))->assertOk()->assertSee('role="progressbar"', false);

    Livewire::actingAs($this->teacher)->test(Index::class)->assertDontSee('role="progressbar"', false);
    $this->actingAs($this->teacher)->get(route('courses.show', $this->course))->assertDontSee('role="progressbar"', false);
});

it('shows an empty note instead of 0 percent when a course has nothing to do yet', function () {
    Livewire::actingAs($this->ani)->test(Index::class)->assertSee('Belum ada tugas atau kuis')->assertDontSee('role="progressbar"', false);
});
