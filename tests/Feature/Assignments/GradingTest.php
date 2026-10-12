<?php

use App\Actions\Assignments\GradeSubmission;
use App\Enums\Role;
use App\Enums\SubmissionStatus;
use App\Livewire\Assignments\Grade;
use App\Livewire\Assignments\Show;
use App\Models\AcademicTerm;
use App\Models\Submission;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->teacher = makeUser(Role::Teacher);
    $this->course = makeCourse($this->teacher);
    $this->assignment = makeAssignment($this->course, ['max_score' => 100]);

    $this->ani = makeUser(Role::Student, ['name' => 'Ani']);
    $this->budi = makeUser(Role::Student, ['name' => 'Budi']);
    $this->citra = makeUser(Role::Student, ['name' => 'Citra']);
    foreach ([$this->ani, $this->budi, $this->citra] as $student) {
        enroll($this->course, $student);
    }

    $this->submit = fn ($student, array $attributes = []) => Submission::create($attributes + [
        'assignment_id' => $this->assignment->id, 'user_id' => $student->id, 'body' => '<p>Jawaban</p>',
        'status' => 'submitted', 'submitted_at' => now(),
    ]);
});

function gradePage($test, $user = null, ?int $student = null): Testable
{
    $params = ['course' => $test->course, 'assignment' => $test->assignment];

    return Livewire::actingAs($user ?? $test->teacher)->withQueryParams($student ? ['siswa' => $student] : [])->test(Grade::class, $params);
}

it('grades a submission', function () {
    ($this->submit)($this->ani);

    gradePage($this, student: $this->ani->id)
        ->set('score', '87.5')->set('feedback', 'Bagus sekali')->call('save')->assertHasNoErrors();

    $submission = Submission::firstOrFail();

    expect($submission->status)->toBe(SubmissionStatus::Graded)
        ->and($submission->score)->toBe(87.5)
        ->and($submission->feedback)->toBe('Bagus sekali')
        ->and($submission->graded_by)->toBe($this->teacher->id)
        ->and($submission->graded_at)->not->toBeNull();
});

it('preloads an existing score and feedback', function () {
    ($this->submit)($this->ani, ['status' => 'graded', 'score' => 90, 'feedback' => 'Mantap']);

    gradePage($this, student: $this->ani->id)->assertSet('score', '90')->assertSet('feedback', 'Mantap');
});

it('validates the score range', function (string $score) {
    ($this->submit)($this->ani);

    gradePage($this, student: $this->ani->id)->set('score', $score)->call('save')->assertHasErrors('score');

    expect(Submission::firstOrFail()->status)->toBe(SubmissionStatus::Submitted);
})->with(['', '-1', '100.01', '1000', 'abc']);

it('accepts the boundary scores', function (string $score) {
    ($this->submit)($this->ani);

    gradePage($this, student: $this->ani->id)->set('score', $score)->call('save')->assertHasNoErrors();

    expect(Submission::firstOrFail()->score)->toBe((float) $score);
})->with(['0', '100']);

it('can grade a student who submitted nothing (paper work)', function () {
    gradePage($this, student: $this->budi->id)->set('score', '75')->call('save')->assertHasNoErrors();

    $submission = Submission::firstOrFail();

    expect($submission->user_id)->toBe($this->budi->id)->and($submission->status)->toBe(SubmissionStatus::Graded)
        ->and($submission->score)->toBe(75.0)->and($submission->body)->toBeNull();
});

it('returns a submission for revision, clearing the score', function () {
    ($this->submit)($this->ani, ['status' => 'graded', 'score' => 40]);

    gradePage($this, student: $this->ani->id)->set('feedback', 'Tambahkan contoh')->call('returnForRevision')->assertHasNoErrors();

    $submission = Submission::firstOrFail();

    expect($submission->status)->toBe(SubmissionStatus::Returned)
        ->and($submission->score)->toBeNull()
        ->and($submission->graded_at)->toBeNull()
        ->and($submission->feedback)->toBe('Tambahkan contoh');
});

it('cannot return something that was never submitted', function () {
    gradePage($this, student: $this->budi->id)->call('returnForRevision')->assertStatus(422);

    expect(Submission::count())->toBe(0);
});

it('lets the student resubmit after it was returned and then regrade', function () {
    ($this->submit)($this->ani);
    gradePage($this, student: $this->ani->id)->set('feedback', 'Revisi ya')->call('returnForRevision');

    Livewire::actingAs($this->ani)->test(Show::class, ['course' => $this->course, 'assignment' => $this->assignment])
        ->set('body', '<p>Sudah direvisi</p>')->call('submit')->assertHasNoErrors();

    expect(Submission::firstOrFail()->status)->toBe(SubmissionStatus::Submitted);

    gradePage($this, student: $this->ani->id)->set('score', '95')->call('save');

    expect(Submission::firstOrFail()->status)->toBe(SubmissionStatus::Graded);
});

it('locks grading when the term is locked by the admin', function () {
    ($this->submit)($this->ani, ['status' => 'graded', 'score' => 70]);
    AcademicTerm::query()->update(['is_locked' => true]);

    gradePage($this, student: $this->ani->id)->set('score', '100')->call('save')->assertForbidden();
    gradePage($this, student: $this->ani->id)->call('returnForRevision')->assertForbidden();
    gradePage($this, student: $this->ani->id)->assertSee(__('assignments.grade.locked_notice'));

    expect(Submission::firstOrFail()->score)->toBe(70.0);
    expect($this->teacher->can('grade', $this->assignment))->toBeFalse();
});

it('also refuses at the action level when the term is locked', function () {
    AcademicTerm::query()->update(['is_locked' => true]);

    expect(fn () => app(GradeSubmission::class)->handle($this->assignment, $this->ani, 50, null, $this->teacher))
        ->toThrow(AuthorizationException::class);

    expect(Submission::count())->toBe(0);
});

it('resumes grading once the admin unlocks the term', function () {
    $term = AcademicTerm::firstOrFail();
    $term->update(['is_locked' => true]);
    expect($this->teacher->can('grade', $this->assignment))->toBeFalse();

    $term->update(['is_locked' => false]);

    expect($this->teacher->fresh()->can('grade', $this->assignment->fresh()))->toBeTrue();
});

it('lets only the teaching teacher or an admin grade', function () {
    ($this->submit)($this->ani);

    gradePage($this, makeUser(Role::Teacher))->assertForbidden();
    gradePage($this, $this->ani)->assertForbidden();

    gradePage($this, makeUser(Role::Admin), $this->ani->id)->set('score', '60')->call('save')->assertHasNoErrors();

    expect(Submission::firstOrFail()->score)->toBe(60.0);
});

it('lets a principal view the grading screen but not grade', function () {
    ($this->submit)($this->ani);
    $principal = makeUser(Role::Principal);

    gradePage($this, $principal, $this->ani->id)->assertOk()->assertSee('Ani')->assertSee(__('assignments.grade.view_only'));
    gradePage($this, $principal, $this->ani->id)->set('score', '50')->call('save')->assertForbidden();

    expect(Submission::firstOrFail()->status)->toBe(SubmissionStatus::Submitted);
});

it('returns 404 for a student who is not in the course', function () {
    $outsider = makeUser(Role::Student);

    gradePage($this)->call('select', $outsider->id)->assertNotFound();
    $this->actingAs($this->teacher)->get(route('assignments.grade', ['course' => $this->course, 'assignment' => $this->assignment, 'siswa' => $outsider->id]))->assertNotFound();
});

it('returns 404 for an assignment of another course', function () {
    $foreign = makeAssignment(makeCourse());

    $this->actingAs($this->teacher)->get("/kelas/{$this->course->id}/tugas/{$foreign->id}/nilai")->assertNotFound();
});

it('lists students with those needing grading first', function () {
    ($this->submit)($this->citra);
    ($this->submit)($this->ani, ['status' => 'graded', 'score' => 80]);

    gradePage($this)->assertSeeInOrder(['Citra', 'Budi', 'Ani'])
        ->assertSee(__('assignments.grade.filter_needs', ['n' => 1]), false);
});

it('filters and searches the student list', function () {
    ($this->submit)($this->citra);
    ($this->submit)($this->ani, ['status' => 'graded', 'score' => 80]);

    gradePage($this)->set('filter', 'needs')->assertSee('Citra')->assertDontSee('Ani')->assertDontSee('Budi')
        ->set('filter', 'graded')->assertSee('Ani')->assertDontSee('Citra')
        ->set('filter', 'missing')->assertSee('Budi')->assertDontSee('Ani')
        ->set('filter', 'all')->set('search', 'bud')->assertSee('Budi')->assertDontSee('Citra');
});

it('moves on to the next student who needs grading', function () {
    ($this->submit)($this->ani);
    ($this->submit)($this->citra);

    gradePage($this, student: $this->ani->id)->set('score', '70')->call('saveAndNext')->assertSet('studentId', $this->citra->id);
});

it('shows the private conversation and lets the teacher reply', function () {
    $this->assignment->comments()->create(['user_id' => $this->ani->id, 'body' => 'Boleh foto, Bu?', 'is_private' => true, 'thread_user_id' => $this->ani->id]);
    $this->assignment->comments()->create(['user_id' => $this->budi->id, 'body' => 'Rahasia Budi', 'is_private' => true, 'thread_user_id' => $this->budi->id]);

    gradePage($this, student: $this->ani->id)->assertSee('Boleh foto, Bu?')->assertDontSee('Rahasia Budi')
        ->set('reply', 'Boleh, asal jelas.')->call('sendReply')->assertHasNoErrors()->assertSee('Boleh, asal jelas.');

    $reply = $this->assignment->comments()->where('body', 'Boleh, asal jelas.')->firstOrFail();

    expect($reply->is_private)->toBeTrue()->and($reply->thread_user_id)->toBe($this->ani->id)->and($reply->user_id)->toBe($this->teacher->id);
});

it('rejects an empty private reply', function () {
    gradePage($this, student: $this->ani->id)->set('reply', '')->call('sendReply')->assertHasErrors('reply');
});
