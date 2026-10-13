<?php

use App\Actions\Attendance\SaveAttendance;
use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Livewire\Attendance\Index;
use App\Livewire\Attendance\Session;
use App\Models\AcademicTerm;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Support\AttendanceSummary;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->teacher = makeUser(Role::Teacher);
    $this->course = makeCourse($this->teacher);
    $this->section = makeSection($this->course);
    $this->ani = makeUser(Role::Student, ['name' => 'Ani']);
    $this->budi = makeUser(Role::Student, ['name' => 'Budi']);
    enroll($this->course, $this->ani);
    enroll($this->course, $this->budi);
    $this->session = AttendanceSession::create(['course_id' => $this->course->id, 'held_on' => now()->toDateString(), 'title' => 'Pertemuan 1']);
});

it('creates a session for a course the teacher manages', function () {
    Livewire::actingAs($this->teacher)->test(Index::class, ['course' => $this->course])
        ->set('held_on', '2030-03-01')->set('section_id', (string) $this->section->id)->set('title', 'Praktikum')
        ->call('createSession')->assertHasNoErrors();

    $created = AttendanceSession::where('title', 'Praktikum')->firstOrFail();

    expect($created->held_on->toDateString())->toBe('2030-03-01')->and($created->section_id)->toBe($this->section->id);
});

it('validates the session form and rejects a foreign section', function () {
    $foreign = makeSection(makeCourse(makeUser(Role::Teacher)));

    Livewire::actingAs($this->teacher)->test(Index::class, ['course' => $this->course])
        ->set('held_on', 'kemarin')->call('createSession')->assertHasErrors(['held_on']);

    Livewire::actingAs($this->teacher)->test(Index::class, ['course' => $this->course])
        ->set('held_on', '2030-03-01')->set('section_id', (string) $foreign->id)->call('createSession')->assertHasErrors(['section_id']);
});

it('saves attendance with upsert and ignores non students', function () {
    $outsider = makeUser(Role::Student);
    $save = app(SaveAttendance::class);

    $count = $save->handle($this->session, [
        $this->ani->id => ['status' => 'present'],
        $this->budi->id => ['status' => 'sick', 'note' => 'Demam'],
        $outsider->id => ['status' => 'present'],
        $this->teacher->id => ['status' => 'present'],
    ]);

    expect($count)->toBe(2)->and(AttendanceRecord::count())->toBe(2);

    $save->handle($this->session, [$this->budi->id => ['status' => 'present']]);

    expect(AttendanceRecord::count())->toBe(2)
        ->and(AttendanceRecord::where('user_id', $this->budi->id)->first()->status)->toBe(AttendanceStatus::Present)
        ->and(AttendanceRecord::where('user_id', $this->budi->id)->first()->note)->toBeNull();
});

it('ignores invalid statuses', function () {
    app(SaveAttendance::class)->handle($this->session, [$this->ani->id => ['status' => 'terlambat']]);

    expect(AttendanceRecord::count())->toBe(0);
});

it('lets the teacher mark everyone present and save from the session page', function () {
    Livewire::actingAs($this->teacher)->test(Session::class, ['course' => $this->course, 'session' => $this->session])
        ->call('markAll', 'present')->call('setStatus', $this->budi->id, 'absent')->set("notes.{$this->budi->id}", 'Tanpa kabar')
        ->call('save')->assertHasNoErrors();

    expect(AttendanceRecord::where('user_id', $this->ani->id)->first()->status)->toBe(AttendanceStatus::Present)
        ->and(AttendanceRecord::where('user_id', $this->budi->id)->first()->status)->toBe(AttendanceStatus::Absent)
        ->and(AttendanceRecord::where('user_id', $this->budi->id)->first()->note)->toBe('Tanpa kabar');
});

it('restores saved statuses when the page is opened again', function () {
    app(SaveAttendance::class)->handle($this->session, [$this->ani->id => ['status' => 'excused']]);

    Livewire::actingAs($this->teacher)->test(Session::class, ['course' => $this->course, 'session' => $this->session])
        ->assertSet("statuses.{$this->ani->id}", 'excused');
});

it('blocks saving when the term is locked', function () {
    AcademicTerm::query()->update(['is_locked' => true]);

    expect(fn () => app(SaveAttendance::class)->handle($this->session->fresh(), [$this->ani->id => ['status' => 'present']]))->toThrow(AuthorizationException::class);

    Livewire::actingAs($this->teacher)->test(Session::class, ['course' => $this->course, 'session' => $this->session])
        ->call('markAll', 'present')->call('save')->assertForbidden();

    Livewire::actingAs($this->teacher)->test(Index::class, ['course' => $this->course])->call('deleteSession', $this->session->id)->assertForbidden();
});

it('does not let students open the fill page or manage sessions', function () {
    $this->actingAs($this->ani)->get(route('attendance.session', ['course' => $this->course, 'session' => $this->session]))->assertForbidden();

    Livewire::actingAs($this->ani)->test(Index::class, ['course' => $this->course])->call('createSession')->assertForbidden();
    Livewire::actingAs($this->ani)->test(Index::class, ['course' => $this->course])->call('deleteSession', $this->session->id)->assertForbidden();
});

it('lets the principal view but not change attendance', function () {
    $principal = makeUser(Role::Principal);

    Livewire::actingAs($principal)->test(Session::class, ['course' => $this->course, 'session' => $this->session])
        ->assertOk()->call('markAll', 'present')->assertForbidden();

    Livewire::actingAs($principal)->test(Session::class, ['course' => $this->course, 'session' => $this->session])->call('save')->assertForbidden();
});

it('keeps outsiders and other teachers away', function () {
    $outsider = makeUser(Role::Student);
    $otherTeacher = makeUser(Role::Teacher);

    foreach ([$outsider, $otherTeacher] as $user) {
        $this->actingAs($user)->get(route('attendance.index', $this->course))->assertForbidden();
        $this->actingAs($user)->get(route('attendance.session', ['course' => $this->course, 'session' => $this->session]))->assertForbidden();
    }
});

it('returns 404 for a session of another course', function () {
    $other = makeCourse($this->teacher);

    $this->actingAs($this->teacher)->get(route('attendance.session', ['course' => $other, 'session' => $this->session]))->assertNotFound();
});

it('summarizes attendance counting only taken sessions', function () {
    $second = AttendanceSession::create(['course_id' => $this->course->id, 'held_on' => now()->subDay()->toDateString()]);
    AttendanceSession::create(['course_id' => $this->course->id, 'held_on' => now()->subDays(2)->toDateString()]); // belum diisi

    $save = app(SaveAttendance::class);
    $save->handle($this->session, [$this->ani->id => ['status' => 'present'], $this->budi->id => ['status' => 'absent']]);
    $save->handle($second, [$this->ani->id => ['status' => 'sick'], $this->budi->id => ['status' => 'present']]);

    $summary = AttendanceSummary::forCourse($this->course);

    expect($summary[$this->ani->id])->toMatchArray(['present' => 1, 'sick' => 1, 'total' => 2, 'percent' => 50.0])
        ->and($summary[$this->budi->id]['percent'])->toBe(50.0);
});

it('shows a student only their own attendance', function () {
    app(SaveAttendance::class)->handle($this->session, [$this->ani->id => ['status' => 'present'], $this->budi->id => ['status' => 'absent']]);

    $component = Livewire::actingAs($this->ani)->test(Index::class, ['course' => $this->course]);

    $component->assertSee('Kehadiranmu')->assertSee('Hadir')->assertSee('100%')->assertDontSee('Budi')->assertDontSee('Isi presensi');
});

it('deletes a session with its records', function () {
    app(SaveAttendance::class)->handle($this->session, [$this->ani->id => ['status' => 'present']]);

    Livewire::actingAs($this->teacher)->test(Index::class, ['course' => $this->course])->call('deleteSession', $this->session->id);

    expect(AttendanceSession::count())->toBe(0)->and(AttendanceRecord::count())->toBe(0);
});
