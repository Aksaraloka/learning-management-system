<?php

use App\Actions\Attendance\SaveAttendance;
use App\Enums\Role;
use App\Enums\SubmissionStatus;
use App\Models\AttendanceSession;
use App\Models\Submission;
use App\Support\Audit;
use App\Support\CourseStats;
use App\Support\Dashboard;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
    $this->teacher = makeUser(Role::Teacher);
    $this->course = makeCourse($this->teacher, ['name' => 'Algoritma Dasar']);
    $this->tugas = $this->course->gradeCategories()->create(['name' => 'Tugas', 'weight' => 100, 'position' => 1]);
    $this->ani = makeUser(Role::Student, ['name' => 'Ani Lestari']);
    $this->budi = makeUser(Role::Student, ['name' => 'Budi Hartono']);
    enroll($this->course, $this->ani);
    enroll($this->course, $this->budi);
});

function graded(object $t, $assignment, $student, float $score): void
{
    Submission::create(['assignment_id' => $assignment->id, 'user_id' => $student->id, 'status' => SubmissionStatus::Graded, 'score' => $score, 'graded_at' => now(), 'body' => 'x']);
}

it('picks the dashboard by role priority', function () {
    expect(Dashboard::viewFor(makeUser(Role::Admin)))->toBe('admin')
        ->and(Dashboard::viewFor(makeUser(Role::Principal)))->toBe('principal')
        ->and(Dashboard::viewFor(makeUser([Role::Teacher, Role::Homeroom])))->toBe('teacher')
        ->and(Dashboard::viewFor(makeUser(Role::Student)))->toBe('student')
        ->and(Dashboard::viewFor(makeUser(Role::Parent)))->toBe('basic')
        ->and(Dashboard::viewFor(makeUser([Role::Admin, Role::Teacher])))->toBe('admin');
});

it('shows a student upcoming deadlines, recent grades and per course grade', function () {
    $due = makeAssignment($this->course, ['title' => 'Tugas Besok', 'grade_category_id' => $this->tugas->id, 'due_at' => now()->addDay()]);
    $done = makeAssignment($this->course, ['title' => 'Tugas Lama', 'grade_category_id' => $this->tugas->id, 'due_at' => now()->subDay(), 'position' => 2]);
    graded($this, $done, $this->ani, 88);
    makeAssignment($this->course, ['title' => 'Tugas Jauh', 'due_at' => now()->addDays(40), 'position' => 3]);
    $quiz = makeQuiz($this->course, ['title' => 'Kuis Segera', 'closes_at' => now()->addDays(2), 'position' => 2]);
    addQuestion($quiz);

    $page = $this->actingAs($this->ani)->get('/')->assertOk();

    $page->assertSee('Tenggat terdekat')->assertSee('Tugas Besok')->assertSee('Kuis Segera')->assertDontSee('Tugas Jauh')
        ->assertSee('Nilai terbaru')->assertSee('Tugas Lama')->assertSee('88')->assertSee('Algoritma Dasar');

    expect($due->id)->not->toBeNull();
});

it('does not list a deadline the student already submitted', function () {
    $a = makeAssignment($this->course, ['title' => 'Sudah Kumpul', 'due_at' => now()->addDay()]);
    Submission::create(['assignment_id' => $a->id, 'user_id' => $this->ani->id, 'body' => 'x', 'status' => SubmissionStatus::Submitted, 'submitted_at' => now()]);

    $this->actingAs($this->ani)->get('/')->assertDontSee('Sudah Kumpul');
    $this->actingAs($this->budi)->get('/')->assertSee('Sudah Kumpul');
});

it('shows a student nothing about classmates', function () {
    $a = makeAssignment($this->course, ['grade_category_id' => $this->tugas->id, 'due_at' => now()->subDay()]);
    graded($this, $a, $this->budi, 61.5);

    $html = $this->actingAs($this->ani)->get('/')->assertOk()->getContent();

    expect($html)->not->toContain('Budi Hartono')->not->toContain('61,5');
});

it('shows the teacher what needs grading and due soon, for own courses only', function () {
    $mine = makeAssignment($this->course, ['title' => 'Perlu Dinilai', 'grade_category_id' => $this->tugas->id, 'due_at' => now()->addDays(2)]);
    Submission::create(['assignment_id' => $mine->id, 'user_id' => $this->ani->id, 'body' => 'x', 'status' => SubmissionStatus::Submitted, 'submitted_at' => now()]);

    $otherCourse = makeCourse(makeUser(Role::Teacher), ['name' => 'Kelas Lain']);
    $other = makeAssignment($otherCourse, ['title' => 'Tugas Kelas Lain', 'due_at' => now()->addDay()]);
    Submission::create(['assignment_id' => $other->id, 'user_id' => makeUser(Role::Student)->id, 'body' => 'x', 'status' => SubmissionStatus::Submitted, 'submitted_at' => now()]);

    $this->actingAs($this->teacher)->get('/')->assertOk()
        ->assertSee('Perlu dinilai')->assertSee('Perlu Dinilai')->assertSee('1 menunggu')->assertSee('1 dari 2 terkumpul')
        ->assertSee('Algoritma Dasar')->assertDontSee('Kelas Lain')->assertDontSee('Tugas Kelas Lain');
});

it('shows the principal every course with statistics and grade distribution', function () {
    $a = makeAssignment($this->course, ['grade_category_id' => $this->tugas->id, 'due_at' => now()->subDay()]);
    graded($this, $a, $this->ani, 90);
    graded($this, $a, $this->budi, 50);
    makeCourse(makeUser(Role::Teacher), ['name' => 'Basis Data']);

    $this->actingAs(makeUser(Role::Principal))->get('/')->assertOk()
        ->assertSee('Algoritma Dasar')->assertSee('Basis Data')->assertSee('Sebaran nilai akhir')->assertSee('Seluruh mata kuliah')
        ->assertSee('70'); // rata-rata (90+50)/2
});

it('shows the admin counts, charts and recent audit', function () {
    Audit::record('term.locked', null, ['name' => 'Ganjil']);

    $this->actingAs(makeUser(Role::Admin))->get('/')->assertOk()
        ->assertSee('Pengguna menurut peran')->assertSee('Mata kuliah per semester')->assertSee('Aktivitas terbaru')->assertSee('Mengunci semester');
});

it('falls back to the basic course list for roles without a dashboard', function () {
    $this->actingAs(makeUser(Role::Parent))->get('/')->assertOk()->assertSee('Mata kuliah saya');
});

it('passes chart data as numbers for the grade charts', function () {
    $a = makeAssignment($this->course, ['grade_category_id' => $this->tugas->id, 'due_at' => now()->subDay()]);
    graded($this, $a, $this->ani, 80);

    $data = Dashboard::student($this->ani);

    expect($data['chart']['labels'])->toBe(['Algoritma Dasar'])->and($data['chart']['values'])->toBe([80.0]);
});

it('buckets final grades into letters', function () {
    expect(CourseStats::letter(100))->toBe('A')->and(CourseStats::letter(85))->toBe('A')->and(CourseStats::letter(84.99))->toBe('B')
        ->and(CourseStats::letter(70))->toBe('B')->and(CourseStats::letter(55))->toBe('C')->and(CourseStats::letter(40))->toBe('D')
        ->and(CourseStats::letter(39.9))->toBe('E')->and(CourseStats::letter(0))->toBe('E');
});

it('summarizes a course: average, buckets, attendance and submission rate', function () {
    $a = makeAssignment($this->course, ['grade_category_id' => $this->tugas->id, 'due_at' => now()->subDay()]);
    graded($this, $a, $this->ani, 90);
    graded($this, $a, $this->budi, 30);
    $session = AttendanceSession::create(['course_id' => $this->course->id, 'held_on' => now()->toDateString()]);
    app(SaveAttendance::class)->handle($session, [$this->ani->id => ['status' => 'present'], $this->budi->id => ['status' => 'absent']]);

    $s = CourseStats::summary($this->course);

    expect($s['students'])->toBe(2)->and($s['avg'])->toBe(60.0)
        ->and($s['buckets'])->toBe(['A' => 1, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 1])
        ->and($s['attendance'])->toBe(50.0)->and($s['submission_rate'])->toBe(100.0);
});

it('returns nulls for a course without data and caches the summary briefly', function () {
    $empty = CourseStats::summary($this->course);

    expect($empty['avg'])->toBeNull()->and($empty['attendance'])->toBeNull()->and($empty['submission_rate'])->toBeNull();

    $a = makeAssignment($this->course, ['grade_category_id' => $this->tugas->id, 'due_at' => now()->subDay()]);
    graded($this, $a, $this->ani, 90);

    expect(CourseStats::summary($this->course)['avg'])->toBeNull(); // masih dari cache

    CourseStats::forget($this->course);

    expect(CourseStats::summary($this->course)['avg'])->toBe(45.0);
});
