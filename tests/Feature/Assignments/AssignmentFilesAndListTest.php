<?php

use App\Enums\Role;
use App\Livewire\Assignments\Index;
use App\Livewire\Courses\Show as CourseShow;
use App\Models\Submission;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');

    $this->teacher = makeUser(Role::Teacher);
    $this->course = makeCourse($this->teacher);
    $this->assignment = makeAssignment($this->course);
    $this->student = makeUser(Role::Student);
    enroll($this->course, $this->student);
});

// --- Berkas ---------------------------------------------------------------------------------------------------

it('serves attachments to class members only', function () {
    Storage::disk('local')->put('courses/1/soal.pdf', pdfBytes());
    $file = $this->assignment->attachments()->create(['file_path' => 'courses/1/soal.pdf', 'file_name' => 'soal.pdf', 'mime_type' => 'application/pdf']);
    $url = route('assignments.attachment', ['assignment' => $this->assignment, 'file' => $file]);

    $response = $this->actingAs($this->student)->get($url)->assertOk();

    expect($response->headers->get('Content-Disposition'))->toStartWith('inline')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');

    $this->actingAs($this->teacher)->get($url)->assertOk();
    $this->actingAs(makeUser(Role::Principal))->get($url)->assertOk();
    $this->actingAs(makeUser(Role::Student))->get($url)->assertForbidden();
    $this->actingAs(makeUser(Role::Teacher))->get($url)->assertForbidden();
});

it('refuses an attachment id from another assignment', function () {
    $other = makeAssignment(makeCourse());
    Storage::disk('local')->put('courses/9/x.pdf', pdfBytes());
    $foreign = $other->attachments()->create(['file_path' => 'courses/9/x.pdf', 'file_name' => 'x.pdf', 'mime_type' => 'application/pdf']);

    $this->actingAs($this->student)->get("/tugas/{$this->assignment->id}/lampiran/{$foreign->id}")->assertNotFound();
});

it('serves a submission file only to its owner, staff and the principal', function () {
    Storage::disk('local')->put('courses/1/jawaban.pdf', pdfBytes());
    $submission = Submission::create([
        'assignment_id' => $this->assignment->id, 'user_id' => $this->student->id, 'status' => 'submitted',
        'file_path' => 'courses/1/jawaban.pdf', 'file_name' => 'jawaban.pdf', 'mime_type' => 'application/pdf',
    ]);
    $classmate = makeUser(Role::Student);
    enroll($this->course, $classmate);

    $this->actingAs($this->student)->get(route('submissions.file', $submission))->assertOk();
    $this->actingAs($this->teacher)->get(route('submissions.file', $submission))->assertOk();
    $this->actingAs(makeUser(Role::Admin))->get(route('submissions.file', $submission))->assertOk();
    $this->actingAs(makeUser(Role::Principal))->get(route('submissions.file', $submission))->assertOk();

    // Teman sekelas, guru lain, dan orang luar tidak boleh membaca pekerjaan siswa.
    $this->actingAs($classmate)->get(route('submissions.file', $submission))->assertForbidden();
    $this->actingAs(makeUser(Role::Teacher))->get(route('submissions.file', $submission))->assertForbidden();
    $this->actingAs(makeUser(Role::Student))->get(route('submissions.file', $submission))->assertForbidden();
});

it('forces a download of non-previewable submission files', function () {
    Storage::disk('local')->put('courses/1/a.zip', 'PK');
    $submission = Submission::create([
        'assignment_id' => $this->assignment->id, 'user_id' => $this->student->id, 'status' => 'submitted',
        'file_path' => 'courses/1/a.zip', 'file_name' => 'a.zip', 'mime_type' => 'application/zip',
    ]);

    $response = $this->actingAs($this->student)->get(route('submissions.file', $submission))->assertOk();

    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment');
});

// --- Daftar tugas ---------------------------------------------------------------------------------------------

it('lists only assignments from the student\'s own courses, nearest deadline first', function () {
    makeAssignment($this->course, ['title' => 'Nanti', 'due_at' => now()->addDays(9), 'position' => 2]);
    makeAssignment($this->course, ['title' => 'Segera', 'due_at' => now()->addDay(), 'position' => 3]);
    makeAssignment($this->course, ['title' => 'Tanpa tenggat', 'due_at' => null, 'position' => 4]);
    makeAssignment(makeCourse(), ['title' => 'Kelas Orang Lain']);

    Livewire::actingAs($this->student)->test(Index::class)
        ->assertSeeInOrder(['Segera', 'Latihan', 'Nanti', 'Tanpa tenggat'])->assertDontSee('Kelas Orang Lain');
});

it('filters the student task list by status', function () {
    $submitted = makeAssignment($this->course, ['title' => 'Sudah Kirim', 'position' => 2]);
    $graded = makeAssignment($this->course, ['title' => 'Sudah Nilai', 'position' => 3]);
    $revise = makeAssignment($this->course, ['title' => 'Harus Revisi', 'position' => 4]);

    foreach ([[$submitted, 'submitted'], [$graded, 'graded'], [$revise, 'returned']] as [$assignment, $status]) {
        Submission::create(['assignment_id' => $assignment->id, 'user_id' => $this->student->id, 'status' => $status, 'score' => $status === 'graded' ? 80 : null]);
    }

    $page = Livewire::actingAs($this->student)->test(Index::class);

    $page->set('filter', 'open')->assertSee('Latihan')->assertDontSee('Sudah Kirim')->assertDontSee('Sudah Nilai')->assertDontSee('Harus Revisi')
        ->set('filter', 'done')->assertSee('Sudah Kirim')->assertDontSee('Latihan')
        ->set('filter', 'graded')->assertSee('Sudah Nilai')->assertDontSee('Sudah Kirim')
        ->set('filter', 'revise')->assertSee('Harus Revisi')->assertDontSee('Sudah Nilai');
});

it('shows a student only their own status and score', function () {
    $classmate = makeUser(Role::Student);
    enroll($this->course, $classmate);
    Submission::create(['assignment_id' => $this->assignment->id, 'user_id' => $classmate->id, 'status' => 'graded', 'score' => 97.25]);

    Livewire::actingAs($this->student)->test(Index::class)->assertSee(__('ui.status.not_submitted'))->assertDontSee('97,25');
});

it('shows teachers how many submissions need grading', function () {
    Submission::create(['assignment_id' => $this->assignment->id, 'user_id' => $this->student->id, 'status' => 'submitted']);

    Livewire::actingAs($this->teacher)->test(Index::class)
        ->assertSee(__('assignments.index.submitted_of', ['done' => 1, 'total' => 1]))
        ->assertSee(__('assignments.index.pending', ['n' => 1]))
        ->set('filter', 'needs')->assertSee('Latihan');

    Submission::query()->update(['status' => 'graded']);

    Livewire::actingAs($this->teacher)->test(Index::class)->set('filter', 'needs')->assertDontSee('Latihan');
});

it('searches and filters by course', function () {
    $second = makeCourse($this->teacher, ['name' => 'Kelas Kedua']);
    makeAssignment($second, ['title' => 'Tugas Kedua']);

    Livewire::actingAs($this->teacher)->test(Index::class)
        ->set('search', 'Kedua')->assertSee('Tugas Kedua')->assertDontSee('Latihan')
        ->set('search', '')->set('course', (string) $this->course->id)->assertSee('Latihan')->assertDontSee('Tugas Kedua');
});

it('shows the task page to every signed-in user and requires login', function () {
    $this->get('/tugas')->assertRedirect(route('login'));
    $this->actingAs($this->student)->get('/tugas')->assertOk();
    $this->actingAs(makeUser(Role::Admin))->get('/tugas')->assertOk();
});

// --- Halaman kelas ---------------------------------------------------------------------------------------------

it('lists assignments on the course page with the student\'s status', function () {
    Submission::create(['assignment_id' => $this->assignment->id, 'user_id' => $this->student->id, 'status' => 'graded', 'score' => 90]);

    Livewire::actingAs($this->student)->test(CourseShow::class, ['course' => $this->course])
        ->assertSee('Latihan')->assertSee(__('ui.status.graded'));

    Livewire::actingAs($this->teacher)->test(CourseShow::class, ['course' => $this->course])
        ->assertSee('Latihan')->assertSee('1/1');
});
