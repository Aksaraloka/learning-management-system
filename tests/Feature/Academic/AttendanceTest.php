<?php

use App\Actions\Academic\SyncAcademicData;
use App\Actions\Attendance\SaveAttendance;
use App\Enums\Role;
use App\Exports\GradebookExport;
use App\Livewire\Attendance\Index;
use App\Livewire\Attendance\Session;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\User;
use App\Support\AttendanceSummary;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    enableAcademic();
    Cache::flush();

    fakeAcademicSync([academicClass()], [10 => [academicStudent(1), academicStudent(2)]]);
    app(SyncAcademicData::class)->handle();

    $this->course = Course::firstOrFail();
    $this->teacher = User::where('academic_user_id', 900)->firstOrFail();
    $this->student1 = User::where('academic_user_id', 1)->firstOrFail();
    $this->student2 = User::where('academic_user_id', 2)->firstOrFail();
});

/**
 * @param  list<array<string, mixed>>|null  $rows  null = Academic gagal (503)
 */
function fakeAcademicAttendance(?array $rows): void
{
    Http::fake([
        'academic.test/api/integration/v1/classes/*/attendance*' => $rows === null
            ? Http::response('galat', 503)
            : Http::response(['data' => $rows]),
    ]);
}

/**
 * @return list<array<string, mixed>>
 */
function attendanceRows(): array
{
    return [
        ['user_id' => 1, 'sessions' => 16, 'present' => 14, 'excused' => 1, 'sick' => 1, 'absent' => 0, 'percent' => 87.5, 'standing' => 'memenuhi'],
        ['user_id' => 2, 'sessions' => 16, 'present' => 8, 'excused' => 0, 'sick' => 0, 'absent' => 8, 'percent' => 50, 'standing' => 'tidak memenuhi'],
    ];
}

it('rejects creating and deleting sessions on a synced class', function () {
    fakeAcademicAttendance(attendanceRows());
    $legacy = AttendanceSession::create(['course_id' => $this->course->id, 'held_on' => '2026-09-01']);

    Livewire::actingAs($this->teacher)->test(Index::class, ['course' => $this->course])
        ->set('held_on', '2026-09-08')->call('createSession')->assertForbidden();
    Livewire::actingAs($this->teacher)->test(Index::class, ['course' => $this->course])
        ->call('deleteSession', $legacy->id)->assertForbidden();

    expect(AttendanceSession::count())->toBe(1);
});

it('rejects filling attendance on a synced class in the component, policy, and action', function () {
    $session = AttendanceSession::create(['course_id' => $this->course->id, 'held_on' => '2026-09-01']);

    Livewire::actingAs($this->teacher)->test(Session::class, ['course' => $this->course, 'session' => $session])
        ->call('setStatus', $this->student1->id, 'present')->assertForbidden();
    Livewire::actingAs($this->teacher)->test(Session::class, ['course' => $this->course, 'session' => $session])
        ->call('save')->assertForbidden();

    expect($this->teacher->can('manageAttendance', $this->course))->toBeFalse()
        ->and(fn () => app(SaveAttendance::class)->handle($session, [$this->student1->id => ['status' => 'present']]))
        ->toThrow(AuthorizationException::class);
});

it('hides the create form and the local session list', function () {
    fakeAcademicAttendance(attendanceRows());

    $this->actingAs($this->teacher)->get(route('attendance.index', $this->course))
        ->assertOk()
        ->assertSee(__('attendance.official'))
        ->assertDontSee(__('attendance.new_session'));
});

it('shows the official recap to the lecturer', function () {
    fakeAcademicAttendance(attendanceRows());

    $this->actingAs($this->teacher)->get(route('attendance.index', $this->course))
        ->assertOk()
        ->assertSee('Mahasiswa 1')->assertSee('Mahasiswa 2')
        ->assertSee('87,5%')->assertSee('50%')
        ->assertSee('memenuhi');
});

it('shows a student only their own row', function () {
    fakeAcademicAttendance(attendanceRows());

    $this->actingAs($this->student1)->get(route('attendance.index', $this->course))
        ->assertOk()
        ->assertSee('87,5%')
        ->assertDontSee('Mahasiswa 2')
        ->assertDontSee('50%')
        ->assertDontSee('tidak memenuhi');

    $component = Livewire::actingAs($this->student1)->test(Index::class, ['course' => $this->course]);
    expect($component->viewData('recap'))->toBeNull()
        ->and($component->viewData('summary')['percent'])->toBe(87.5);
});

it('shows an unavailable state instead of a 500 when Academic is down', function () {
    fakeAcademicAttendance(null);
    makeAssignment($this->course);

    $this->actingAs($this->teacher)->get(route('attendance.index', $this->course))
        ->assertOk()->assertSee(__('attendance.unavailable'));
    $this->actingAs($this->student1)->get(route('attendance.index', $this->course))
        ->assertOk()->assertSee(__('attendance.unavailable'));
    $this->actingAs($this->teacher)->get(route('gradebook.index', $this->course))
        ->assertOk()->assertSee(__('attendance.unavailable'));
});

it('does not retry a failing Academic on every page view', function () {
    fakeAcademicAttendance(null);

    $this->actingAs($this->teacher)->get(route('attendance.index', $this->course))->assertOk();
    $sent = Http::recorded()->count();
    $this->actingAs($this->teacher)->get(route('attendance.index', $this->course))->assertOk();

    expect(Http::recorded()->count())->toBe($sent);
});

it('treats an unreachable Academic like an unavailable one', function () {
    Http::fake(['academic.test/api/integration/v1/classes/*/attendance*' => fn () => throw new ConnectionException('timeout')]);

    $this->actingAs($this->teacher)->get(route('attendance.index', $this->course))->assertOk()->assertSee(__('attendance.unavailable'));
});

it('caches the Academic recap and maps it to Learn user ids', function () {
    fakeAcademicAttendance(attendanceRows());

    $first = AttendanceSummary::forCourse($this->course);
    $calls = Http::recorded()->count();
    $second = AttendanceSummary::forCourse($this->course);

    expect($first[$this->student1->id])->toMatchArray(['present' => 14, 'excused' => 1, 'sick' => 1, 'absent' => 0, 'total' => 16, 'percent' => 87.5, 'standing' => 'memenuhi'])
        ->and($first[$this->student2->id]['percent'])->toBe(50.0)
        ->and($second)->toBe($first)
        ->and(Http::recorded()->count())->toBe($calls);
});

it('uses the same source for the gradebook column and the export', function () {
    fakeAcademicAttendance(attendanceRows());
    makeAssignment($this->course);

    $this->actingAs($this->teacher)->get(route('gradebook.index', $this->course))->assertOk()->assertSee('87,5%');

    $flat = json_encode((new GradebookExport($this->course))->array());

    expect($flat)->toContain('87.5')->and($flat)->toContain('50');
});

it('gives a student only their own attendance in the gradebook', function () {
    fakeAcademicAttendance(attendanceRows());

    $component = Livewire::actingAs($this->student2)->test(App\Livewire\Gradebook\Index::class, ['course' => $this->course]);

    expect(array_keys($component->viewData('attendance')))->toBe([$this->student2->id]);
});

it('keeps local attendance for manually created courses even when the integration is on', function () {
    $teacher = makeUser(Role::Teacher);
    $manual = makeCourse($teacher);

    expect($teacher->can('manageAttendance', $manual))->toBeTrue();

    Livewire::actingAs($teacher)->test(Index::class, ['course' => $manual])
        ->set('held_on', '2026-09-08')->call('createSession')->assertHasNoErrors();

    expect(AttendanceSession::where('course_id', $manual->id)->count())->toBe(1);
});
