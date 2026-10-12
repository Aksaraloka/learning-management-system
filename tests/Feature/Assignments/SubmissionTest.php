<?php

use App\Enums\EnrollmentRole;
use App\Enums\Role;
use App\Enums\SubmissionStatus;
use App\Livewire\Assignments\Show;
use App\Models\Submission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');

    $this->teacher = makeUser(Role::Teacher);
    $this->course = makeCourse($this->teacher);
    $this->student = makeUser(Role::Student);
    enroll($this->course, $this->student);
    $this->assignment = makeAssignment($this->course, ['due_at' => now()->addDays(2)]);
});

function asStudent($test, $student = null): Testable
{
    return Livewire::actingAs($student ?? $test->student)->test(Show::class, ['course' => $test->course, 'assignment' => $test->assignment]);
}

it('submits a text answer', function () {
    asStudent($this)->set('body', '<p>Jawaban saya <strong>lengkap</strong></p>')->call('submit')->assertHasNoErrors();

    $submission = Submission::firstOrFail();

    expect($submission->user_id)->toBe($this->student->id)
        ->and($submission->status)->toBe(SubmissionStatus::Submitted)
        ->and($submission->is_late)->toBeFalse()
        ->and($submission->body)->toContain('<strong>lengkap</strong>')
        ->and($submission->submitted_at)->not->toBeNull();
});

it('sanitizes the submitted HTML', function () {
    asStudent($this)->set('body', '<p>Halo</p><script>alert(1)</script><img src=x onerror=alert(2)>')->call('submit');

    expect(Submission::firstOrFail()->body)->toContain('Halo')->not->toContain('script')->not->toContain('onerror')->not->toContain('<img');
});

it('submits a file stored privately', function () {
    asStudent($this)->set('upload', UploadedFile::fake()->createWithContent('Jawaban Budi.pdf', pdfBytes()))->call('submit')->assertHasNoErrors();

    $submission = Submission::firstOrFail();

    expect($submission->file_name)->toBe('Jawaban Budi.pdf')
        ->and($submission->file_path)->toStartWith("courses/{$this->course->id}/assignments/{$this->assignment->id}/submissions/")
        ->and($submission->file_path)->not->toContain('Budi');

    Storage::disk('local')->assertExists($submission->file_path);
});

it('rejects an empty submission', function () {
    asStudent($this)->call('submit')->assertHasErrors('body');
    asStudent($this)->set('body', '<p> </p>')->call('submit')->assertHasErrors('body');

    expect(Submission::count())->toBe(0);
});

it('rejects dangerous files', function () {
    asStudent($this)->set('upload', UploadedFile::fake()->createWithContent('virus.exe', 'MZ'.str_repeat("\0", 64)))->call('submit')->assertHasErrors('upload');
    asStudent($this)->set('upload', UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;'))->call('submit')->assertHasErrors('upload');

    expect(Submission::count())->toBe(0);
});

it('replaces the submission before the deadline and deletes the old file', function () {
    asStudent($this)->set('upload', UploadedFile::fake()->createWithContent('lama.pdf', pdfBytes()))->call('submit');
    $old = Submission::firstOrFail()->file_path;

    asStudent($this)->set('body', '<p>Revisi</p>')->set('upload', UploadedFile::fake()->createWithContent('baru.pdf', pdfBytes()))->call('submit')->assertHasNoErrors();

    $submission = Submission::firstOrFail();

    expect(Submission::count())->toBe(1)
        ->and($submission->file_name)->toBe('baru.pdf')
        ->and($submission->file_path)->not->toBe($old)
        ->and($submission->body)->toContain('Revisi');

    Storage::disk('local')->assertMissing($old);
    Storage::disk('local')->assertExists($submission->file_path);
});

it('keeps the existing file when only the text is changed', function () {
    asStudent($this)->set('upload', UploadedFile::fake()->createWithContent('a.pdf', pdfBytes()))->call('submit');
    $path = Submission::firstOrFail()->file_path;

    asStudent($this)->set('body', '<p>Tambahan</p>')->call('submit')->assertHasNoErrors();

    expect(Submission::firstOrFail()->file_path)->toBe($path);
    Storage::disk('local')->assertExists($path);
});

it('closes submissions after the deadline unless late work is allowed', function () {
    $this->assignment->update(['due_at' => now()->subHour()]);

    asStudent($this)->set('body', '<p>Telat</p>')->call('submit')->assertHasErrors('body');

    expect(Submission::count())->toBe(0);
});

it('accepts late work as late when allowed', function () {
    $this->assignment->update(['due_at' => now()->subHour(), 'allow_late' => true]);

    asStudent($this)->set('body', '<p>Telat tapi boleh</p>')->call('submit')->assertHasNoErrors();

    $submission = Submission::firstOrFail();

    expect($submission->status)->toBe(SubmissionStatus::Late)->and($submission->is_late)->toBeTrue();
});

it('has no deadline restriction without a due date', function () {
    $this->assignment->update(['due_at' => null]);

    asStudent($this)->set('body', '<p>Kapan saja</p>')->call('submit')->assertHasNoErrors();

    expect(Submission::firstOrFail()->status)->toBe(SubmissionStatus::Submitted);
});

it('locks a graded submission', function () {
    Submission::create(['assignment_id' => $this->assignment->id, 'user_id' => $this->student->id, 'body' => '<p>x</p>', 'status' => 'graded', 'score' => 90]);

    asStudent($this)->set('body', '<p>Ubah nilai!</p>')->call('submit')->assertHasErrors('body');

    $submission = Submission::firstOrFail();

    expect($submission->body)->toBe('<p>x</p>')->and($submission->score)->toBe(90.0)->and($submission->status)->toBe(SubmissionStatus::Graded);
});

it('lets a student resubmit a returned submission even after the deadline', function () {
    Submission::create(['assignment_id' => $this->assignment->id, 'user_id' => $this->student->id, 'body' => '<p>v1</p>', 'status' => 'returned', 'feedback' => 'Lengkapi']);
    $this->assignment->update(['due_at' => now()->subDay(), 'allow_late' => false]);

    asStudent($this)->assertSet('body', '<p>v1</p>')->set('body', '<p>v2</p>')->call('submit')->assertHasNoErrors();

    $submission = Submission::firstOrFail();

    expect($submission->body)->toBe('<p>v2</p>')
        ->and($submission->status)->toBe(SubmissionStatus::Late)
        ->and($submission->feedback)->toBe('Lengkapi')
        ->and($submission->score)->toBeNull();
});

it('forbids non-members from opening or submitting', function () {
    $outsider = makeUser(Role::Student);

    $this->actingAs($outsider)->get("/kelas/{$this->course->id}/tugas/{$this->assignment->id}")->assertForbidden();
    asStudent($this, $outsider)->assertForbidden();
});

it('does not let teachers or non-enrolled students submit', function () {
    $this->actingAs($this->teacher);
    expect($this->teacher->can('submit', $this->assignment))->toBeFalse();

    $outsider = makeUser(Role::Student);
    expect($outsider->can('submit', $this->assignment))->toBeFalse();

    $principal = makeUser(Role::Principal);
    expect($principal->can('submit', $this->assignment))->toBeFalse();

    $teacherAsStudent = makeUser(Role::Teacher);
    enroll($this->course, $teacherAsStudent, EnrollmentRole::Student);
    expect($teacherAsStudent->can('submit', $this->assignment))->toBeFalse()->and($this->student->can('submit', $this->assignment))->toBeTrue();
});

it('shows the student their score and feedback only', function () {
    $other = makeUser(Role::Student, ['name' => 'Siswa Lain']);
    enroll($this->course, $other);
    Submission::create(['assignment_id' => $this->assignment->id, 'user_id' => $this->student->id, 'body' => '<p>punya saya</p>', 'status' => 'graded', 'score' => 88, 'feedback' => 'Catatan untuk saya']);
    Submission::create(['assignment_id' => $this->assignment->id, 'user_id' => $other->id, 'body' => '<p>punya orang lain</p>', 'status' => 'graded', 'score' => 55, 'feedback' => 'Catatan rahasia']);

    asStudent($this)->assertSee('88')->assertSee('Catatan untuk saya')->assertDontSee('55')->assertDontSee('Catatan rahasia')->assertDontSee('punya orang lain');
});
