<?php

use App\Actions\Academic\SyncAcademicData;
use App\Enums\Role;
use App\Livewire\Courses\Form;
use App\Livewire\Courses\Index;
use App\Livewire\Courses\Members;
use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    enableAcademic();
    Cache::flush();
});

it('syncs term, class, teachers, and students and is idempotent', function () {
    fakeAcademicSync([academicClass()], [10 => [academicStudent(1), academicStudent(2)]]);

    $first = app(SyncAcademicData::class)->handle();
    $second = app(SyncAcademicData::class)->handle();

    expect($first)->toBe(['terms' => 1, 'classes' => 1, 'hidden' => 0, 'failed' => 0])->and($second)->toBe($first)
        ->and(AcademicTerm::count())->toBe(1)
        ->and(Course::count())->toBe(1)
        ->and(User::count())->toBe(3)
        ->and(Enrollment::count())->toBe(3);

    $term = AcademicTerm::firstOrFail();
    $course = Course::firstOrFail();

    expect($term->name)->toBe('2026/2027 Ganjil')->and($term->academic_term_id)->toBe(1)
        ->and($course->name)->toBe('Basis Data A')
        ->and($course->code)->toBe('IF101')
        ->and($course->credits)->toBe(3)
        ->and($course->academic_term_id)->toBe($term->id)
        ->and($course->academic_class_id)->toBe(10)
        ->and($course->gradeCategories()->count())->toBe(3)
        ->and($course->teachers()->pluck('users.name')->all())->toBe(['Dr. Dewi'])
        ->and($course->students()->count())->toBe(2);

    $teacher = User::where('academic_user_id', 900)->firstOrFail();
    expect($teacher->hasAppRole(Role::Teacher))->toBeTrue()
        ->and(User::where('academic_user_id', 1)->firstOrFail()->hasAppRole(Role::Student))->toBeTrue();
});

it('links synced people to existing accounts instead of duplicating them', function () {
    $existing = makeUser(Role::Student, ['email' => 'mhs1@kampus.test', 'identity_number' => null]);

    fakeAcademicSync([academicClass()], [10 => [academicStudent(1)]]);

    app(SyncAcademicData::class)->handle();

    expect(User::where('email', 'mhs1@kampus.test')->count())->toBe(1)
        ->and($existing->fresh()->academic_user_id)->toBe(1)
        ->and(Course::firstOrFail()->hasMember($existing))->toBeTrue();
});

it('revokes enrollment of a student who left the roster but keeps their submissions', function () {
    fakeAcademicSync([academicClass()], [10 => [academicStudent(1), academicStudent(2)]]);
    app(SyncAcademicData::class)->handle();

    $course = Course::firstOrFail();
    $leaver = User::where('academic_user_id', 2)->firstOrFail();
    $assignment = makeAssignment($course);
    $submission = Submission::create(['assignment_id' => $assignment->id, 'user_id' => $leaver->id, 'status' => 'graded', 'score' => 80]);

    fakeAcademicSync([academicClass()], [10 => [academicStudent(1)]]);
    app(SyncAcademicData::class)->handle();

    expect($course->hasMember($leaver))->toBeFalse()
        ->and($course->students()->count())->toBe(1)
        ->and(Submission::find($submission->id))->not->toBeNull()
        ->and($this->actingAs($leaver)->get(route('courses.show', $course))->status())->toBe(403);
});

it('removes a manually added member because Academic owns the roster', function () {
    fakeAcademicSync([academicClass()], [10 => [academicStudent(1)]]);
    app(SyncAcademicData::class)->handle();

    $course = Course::firstOrFail();
    $manual = makeUser(Role::Student);
    enroll($course, $manual);

    app(SyncAcademicData::class)->handle();

    expect($course->hasMember($manual))->toBeFalse();
});

it('hides a class that disappeared from Academic and restores it when it returns', function () {
    fakeAcademicSync([academicClass(10), academicClass(11, ['subject' => ['code' => 'IF102', 'name' => 'Jaringan', 'credits' => 2]])], []);
    app(SyncAcademicData::class)->handle();

    expect(Course::count())->toBe(2);

    fakeAcademicSync([academicClass(10)], []);
    $result = app(SyncAcademicData::class)->handle();

    expect($result['hidden'])->toBe(1)
        ->and(Course::count())->toBe(1)
        ->and(Course::withTrashed()->where('academic_class_id', 11)->firstOrFail()->trashed())->toBeTrue();

    fakeAcademicSync([academicClass(10), academicClass(11, ['subject' => ['code' => 'IF102', 'name' => 'Jaringan', 'credits' => 2]])], []);
    app(SyncAcademicData::class)->handle();

    expect(Course::count())->toBe(2)->and(Course::withTrashed()->count())->toBe(2);
});

it('falls back to a generated join code when the subject code is already taken', function () {
    fakeAcademicSync([academicClass(10), academicClass(11, ['section' => 'B', 'class_label' => 'B'])], []);

    app(SyncAcademicData::class)->handle();

    $codes = Course::orderBy('academic_class_id')->pluck('code')->all();

    expect($codes[0])->toBe('IF101')->and($codes[1])->not->toBe('IF101')->and($codes[1])->toHaveLength(6);
});

it('keeps description and content when the class is synced again', function () {
    fakeAcademicSync([academicClass()], []);
    app(SyncAcademicData::class)->handle();

    $course = Course::firstOrFail();
    $course->update(['description' => 'Deskripsi dosen']);
    makeSection($course, 'Pertemuan 1');

    fakeAcademicSync([academicClass(10, ['class_label' => 'A1'])], []);
    app(SyncAcademicData::class)->handle();

    $course->refresh();
    expect($course->description)->toBe('Deskripsi dosen')->and($course->name)->toBe('Basis Data A1')->and($course->sections()->count())->toBe(1);
});

it('skips the classes of an already synced locked term unless asked', function () {
    fakeAcademicSync([academicClass()], [10 => [academicStudent(1)]]);
    app(SyncAcademicData::class)->handle();

    fakeAcademicSync([academicClass(), academicClass(12, ['subject' => ['code' => 'IF200', 'name' => 'Baru', 'credits' => 2]])], [], ['is_locked' => true]);

    expect(app(SyncAcademicData::class)->handle()['classes'])->toBe(0)
        ->and(AcademicTerm::firstOrFail()->is_locked)->toBeTrue()
        ->and(app(SyncAcademicData::class)->handle(includeLocked: true)['classes'])->toBe(2);
});

it('does nothing from the command when the integration is off', function () {
    config(['academic.enabled' => false]);
    Http::fake();

    $this->artisan('academic:sync')->assertSuccessful();

    Http::assertNothingSent();
    expect(Course::count())->toBe(0);
});

it('runs the sync from the command', function () {
    fakeAcademicSync([academicClass()], [10 => [academicStudent(1)]]);

    $this->artisan('academic:sync', ['--term' => 1])->assertSuccessful();

    expect(Course::count())->toBe(1);
});

describe('ownership of synced classes', function () {
    beforeEach(function () {
        fakeAcademicSync([academicClass()], [10 => [academicStudent(1)]]);
        app(SyncAcademicData::class)->handle();

        $this->course = Course::firstOrFail();
        $this->teacher = User::where('academic_user_id', 900)->firstOrFail();
        $this->student = User::where('academic_user_id', 1)->firstOrFail();
    });

    it('locks name, credits, and term but lets the lecturer edit the description', function () {
        $termId = $this->course->academic_term_id;

        Livewire::actingAs($this->teacher)->test(Form::class, ['course' => $this->course])
            ->set('name', 'Diubah')->set('credits', '4')->set('description', 'Deskripsi baru')
            ->call('save')->assertHasNoErrors();

        $this->course->refresh();
        expect($this->course->name)->toBe('Basis Data A')
            ->and($this->course->credits)->toBe(3)
            ->and($this->course->academic_term_id)->toBe($termId)
            ->and($this->course->description)->toBe('Deskripsi baru');
    });

    it('only lets an admin create courses manually', function () {
        $this->actingAs($this->teacher)->get(route('courses.create'))->assertForbidden();
        $this->actingAs(makeUser(Role::Admin))->get(route('courses.create'))->assertOk();
    });

    it('lets teachers create courses again when the integration is off', function () {
        config(['academic.enabled' => false]);

        $this->actingAs($this->teacher)->get(route('courses.create'))->assertOk();
    });

    it('makes the member page read-only', function () {
        $this->actingAs($this->teacher)->get(route('courses.members', $this->course))->assertOk()->assertSee(__('courses.members.managed'));

        Livewire::actingAs($this->teacher)->test(Members::class, ['course' => $this->course])
            ->set('identifiers', 'x@y.test')->call('add')->assertForbidden();
        Livewire::actingAs($this->teacher)->test(Members::class, ['course' => $this->course])
            ->call('remove', $this->student->id)->assertForbidden();

        expect($this->course->hasMember($this->student))->toBeTrue();
    });

    it('does not let students join a synced class with its code', function () {
        $other = makeUser(Role::Student);

        Livewire::actingAs($other)->test(Index::class)->set('code', $this->course->code)->call('join')->assertHasErrors('code');

        expect($this->course->hasMember($other))->toBeFalse();
    });

    it('lets only admins delete a synced class', function () {
        expect($this->teacher->can('delete', $this->course))->toBeFalse()
            ->and(makeUser(Role::Admin)->can('delete', $this->course))->toBeTrue();
    });

    it('stops managing a synced class when the integration is off', function () {
        config(['academic.enabled' => false]);

        expect($this->teacher->can('manageMembers', $this->course->fresh()))->toBeTrue();
    });

    it('blocks admins from editing or locking a synced term', function () {
        $admin = makeUser(Role::Admin);
        $term = AcademicTerm::firstOrFail();

        expect($admin->can('update', $term))->toBeFalse()->and($admin->can('lock', $term))->toBeFalse();
    });
});

describe('class link /kelas/academic/{id}', function () {
    beforeEach(function () {
        fakeAcademicSync([academicClass()], [10 => [academicStudent(1)]]);
    });

    it('syncs a class that does not exist yet and sends a member to it', function () {
        // Sinkron sungguhan lewat tautan; peserta harus ada di roster.
        $member = makeUser(Role::Student, ['email' => 'mhs1@kampus.test', 'identity_number' => null]);

        $this->actingAs($member)->get(route('courses.academic', 10))->assertRedirect(route('courses.show', Course::firstOrFail()));

        expect(Course::count())->toBe(1);
    });

    it('sends a member of an already synced class straight to it', function () {
        app(SyncAcademicData::class)->handle();
        $teacher = User::where('academic_user_id', 900)->firstOrFail();
        $sent = Http::recorded()->count();

        $this->actingAs($teacher)->get(route('courses.academic', 10))->assertRedirect(route('courses.show', Course::firstOrFail()));

        expect(Http::recorded()->count())->toBe($sent);
    });

    it('answers 403 to someone who is not a member', function () {
        app(SyncAcademicData::class)->handle();

        $this->actingAs(makeUser(Role::Student))->get(route('courses.academic', 10))->assertForbidden();
    });

    it('answers 404 when the class does not exist in Academic', function () {
        $this->actingAs(makeUser(Role::Student))->get(route('courses.academic', 999))->assertNotFound();
    });

    it('requires login', function () {
        $this->get(route('courses.academic', 10))->assertRedirect(route('login'));
    });

    it('is not available when the integration is off', function () {
        config(['academic.enabled' => false]);

        $this->actingAs(makeUser(Role::Admin))->get(route('courses.academic', 10))->assertNotFound();
    });
});
