<?php

use App\Actions\Assignments\GradeSubmission;
use App\Actions\Attendance\SaveAttendance;
use App\Actions\Gradebook\UpdateGradeCategories;
use App\Actions\Quizzes\FinishAttempt;
use App\Actions\Quizzes\GradeEssays;
use App\Actions\Quizzes\RecomputeAttempts;
use App\Actions\Quizzes\StartAttempt;
use App\Actions\Users\ImportUsers;
use App\Enums\QuestionType;
use App\Enums\Role;
use App\Livewire\Admin\Audit\Index;
use App\Livewire\Admin\Terms\Index as Terms;
use App\Livewire\Admin\Users\Index as Users;
use App\Livewire\Courses\Show;
use App\Models\AcademicTerm;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use App\Models\ImportRun;
use App\Support\Audit;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->teacher = makeUser(Role::Teacher);
    $this->course = makeCourse($this->teacher);
    $this->student = makeUser(Role::Student);
    enroll($this->course, $this->student);
    $this->admin = makeUser(Role::Admin);
});

it('records who graded a submission with the old and new score', function () {
    $assignment = makeAssignment($this->course);
    $this->actingAs($this->teacher);

    app(GradeSubmission::class)->handle($assignment, $this->student, 70, null, $this->teacher);
    app(GradeSubmission::class)->handle($assignment, $this->student, 85.5, 'Revisi nilai', $this->teacher);

    $logs = AuditLog::where('action', 'submission.graded')->orderBy('id')->get();

    expect($logs)->toHaveCount(2)
        ->and($logs[0]->user_id)->toBe($this->teacher->id)
        ->and($logs[0]->course_id)->toBe($this->course->id)
        ->and($logs[0]->meta['from'])->toBeNull()->and($logs[0]->meta['to'])->toEqual(70)
        ->and($logs[1]->meta['from'])->toEqual(70)->and($logs[1]->meta['to'])->toEqual(85.5)
        ->and($logs[1]->subject_type)->toBe('Submission');
});

it('records returning a submission for revision', function () {
    $assignment = makeAssignment($this->course);
    $submission = app(GradeSubmission::class)->handle($assignment, $this->student, 60, null, $this->teacher);

    app(GradeSubmission::class)->returnForRevision($assignment, $submission, 'Perbaiki');

    expect(AuditLog::where('action', 'submission.returned')->first()->meta['from'])->toEqual(60);
});

it('does not write an audit entry when grading is rejected', function () {
    $assignment = makeAssignment($this->course);

    expect(fn () => app(GradeSubmission::class)->handle($assignment, $this->student, 500, null, $this->teacher))->toThrow(ValidationException::class);
    expect(AuditLog::count())->toBe(0);
});

it('records essay grading and quiz recomputation', function () {
    $quiz = makeQuiz($this->course);
    addQuestion($quiz, QuestionType::TrueFalse);
    $essay = addQuestion($quiz, QuestionType::Essay, ['points' => 5]);
    $attempt = app(StartAttempt::class)->handle($quiz->fresh(), $this->student);
    app(FinishAttempt::class)->handle($attempt);

    app(GradeEssays::class)->handle($attempt->fresh(), [$essay->id => 4.0]);
    app(RecomputeAttempts::class)->handle($quiz);

    $graded = AuditLog::where('action', 'quiz.essay_graded')->first();

    expect($graded->meta['to'])->toEqual(4)->and($graded->course_id)->toBe($this->course->id)
        ->and(AuditLog::where('action', 'quiz.recomputed')->first()->meta['attempts'])->toBe(1);
});

it('records weight changes only when something changed', function () {
    $tugas = $this->course->gradeCategories()->create(['name' => 'Tugas', 'weight' => 60, 'position' => 1]);
    $kuis = $this->course->gradeCategories()->create(['name' => 'Kuis', 'weight' => 40, 'position' => 2]);
    $same = [$tugas->id => ['name' => 'Tugas', 'weight' => '60'], $kuis->id => ['name' => 'Kuis', 'weight' => '40']];

    app(UpdateGradeCategories::class)->handle($this->course, $same);
    expect(AuditLog::count())->toBe(0);

    app(UpdateGradeCategories::class)->handle($this->course, [$tugas->id => ['name' => 'Tugas', 'weight' => '50'], $kuis->id => ['name' => 'Kuis', 'weight' => '50']]);

    $log = AuditLog::first();

    expect($log->action)->toBe('gradebook.weights_updated')->and($log->meta['from'])->toBe(['Tugas' => 60, 'Kuis' => 40])->and($log->meta['to'])->toBe(['Tugas' => 50, 'Kuis' => 50]);
});

it('records attendance saves', function () {
    $session = AttendanceSession::create(['course_id' => $this->course->id, 'held_on' => '2030-01-01']);

    app(SaveAttendance::class)->handle($session, [$this->student->id => ['status' => 'present']]);

    expect(AuditLog::first()->meta)->toBe(['students' => 1, 'date' => '2030-01-01']);
});

it('records term locking, unlocking and deletion by the admin', function () {
    $term = AcademicTerm::query()->first() ?? $this->course->term;

    Livewire::actingAs($this->admin)->test(Terms::class)->call('toggleLock', $term->id)->call('toggleLock', $term->id);

    expect(AuditLog::pluck('action')->all())->toBe(['term.locked', 'term.unlocked'])
        ->and(AuditLog::first()->user_id)->toBe($this->admin->id);
});

it('records password resets without storing the password', function () {
    Livewire::actingAs($this->admin)->test(Users::class)->call('resetPassword', $this->student->id);

    $log = AuditLog::where('action', 'user.password_reset')->firstOrFail();

    expect($log->subject_id)->toBe($this->student->id)->and(json_encode($log->meta))->not->toContain('password');
});

it('records user imports under the uploader', function () {
    $run = ImportRun::create(['user_id' => $this->admin->id, 'type' => 'users', 'file_path' => 'x.csv', 'status' => 'pending']);

    app(ImportUsers::class)->handle($run, [['name' => 'Rani', 'identity_number' => '999001', 'email' => 'rani@kampus.test', 'role' => 'mahasiswa', 'password' => '']]);

    $log = AuditLog::where('action', 'users.imported')->firstOrFail();

    expect($log->user_id)->toBe($this->admin->id)->and($log->meta['success'])->toBe(1);
});

it('records course deletion', function () {
    Livewire::actingAs($this->teacher)->test(Show::class, ['course' => $this->course])->call('deleteCourse');

    $log = AuditLog::where('action', 'course.deleted')->firstOrFail();

    expect($log->meta['name'])->toBe($this->course->name);
});

it('cannot be edited or deleted through the application', function () {
    $log = Audit::record('test.action', null, ['a' => 1]);

    expect(fn () => $log->update(['action' => 'x']))->toThrow(LogicException::class)
        ->and(fn () => $log->delete())->toThrow(LogicException::class)
        ->and(AuditLog::count())->toBe(1);
});

it('keeps the actor and ip, and allows system actions without a user', function () {
    $this->actingAs($this->admin);
    expect(Audit::record('a.b')->user_id)->toBe($this->admin->id);

    auth()->logout();
    expect(Audit::record('a.c')->user_id)->toBeNull();
});

it('lets admin and principal view the log but nobody else', function () {
    Audit::record('term.locked', null, ['name' => 'Ganjil']);

    Livewire::actingAs($this->admin)->test(Index::class)->assertOk()->assertSee('Mengunci semester');
    Livewire::actingAs(makeUser(Role::Principal))->test(Index::class)->assertOk()->assertSee('Mengunci semester');

    foreach ([$this->teacher, $this->student] as $user) {
        $this->actingAs($user)->get(route('admin.audit.index'))->assertForbidden();
    }

    auth()->logout();
    $this->get(route('admin.audit.index'))->assertRedirect(route('login'));
});

it('filters the log by action, course and user name', function () {
    $other = makeCourse(makeUser(Role::Teacher));
    $this->actingAs($this->teacher);
    Audit::record('attendance.saved', null, ['students' => 1], $this->course->id);
    $this->actingAs($this->admin);
    Audit::record('term.locked', null, ['name' => 'Genap']);
    Audit::record('attendance.saved', null, ['students' => 9], $other->id);

    $page = fn () => Livewire::actingAs($this->admin)->test(Index::class);

    $page()->set('action', 'term.locked')->assertSee('Genap')->assertDontSee('Mahasiswa: 1')->assertDontSee('Mahasiswa: 9');
    $page()->set('course', (string) $this->course->id)->assertSee('Mahasiswa: 1')->assertDontSee('Mahasiswa: 9')->assertDontSee('Genap');
    $page()->set('search', $this->teacher->name)->assertSee('Mahasiswa: 1')->assertDontSee('Genap');
});

it('shows score changes as from to', function () {
    Audit::record('submission.graded', null, ['from' => 70.0, 'to' => 85.5, 'assignment' => 'Latihan']);

    Livewire::actingAs($this->admin)->test(Index::class)->assertSee('70 → 85,5')->assertSee('Latihan');
});
