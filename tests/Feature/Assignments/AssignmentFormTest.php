<?php

use App\Enums\Role;
use App\Livewire\Assignments\Form;
use App\Livewire\Assignments\Show;
use App\Models\Assignment;
use App\Models\AssignmentFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');

    $this->teacher = makeUser(Role::Teacher);
    $this->course = makeCourse($this->teacher);
    $this->section = makeSection($this->course, 'Bab 1');
    $this->category = $this->course->gradeCategories()->create(['name' => 'Tugas', 'weight' => 40, 'position' => 1]);
});

function assignmentForm($test, array $props = []): Testable
{
    return Livewire::actingAs($test->teacher)->test(Form::class, ['course' => $test->course] + $props);
}

it('creates an assignment and sanitizes the instructions', function () {
    assignmentForm($this)
        ->set('section_id', (string) $this->section->id)->set('title', 'Latihan Aljabar')
        ->set('instructions', '<p>Kerjakan <strong>semua</strong></p><script>alert(1)</script><a href="javascript:x()">x</a>')
        ->set('due_at', '2026-12-20 23:59')->set('max_score', '80')->set('allow_late', true)
        ->call('save')->assertHasNoErrors();

    $assignment = Assignment::firstOrFail();

    expect($assignment->title)->toBe('Latihan Aljabar')
        ->and($assignment->course_id)->toBe($this->course->id)
        ->and($assignment->max_score)->toBe(80)
        ->and($assignment->allow_late)->toBeTrue()
        ->and($assignment->grade_category_id)->toBe($this->category->id)
        ->and($assignment->instructions)->toContain('<strong>semua</strong>')->not->toContain('script')->not->toContain('javascript')
        ->and($assignment->due_at->timezone(config('app.timezone'))->format('Y-m-d H:i'))->toBe('2026-12-20 23:59');
});

it('goes to the edit page after creating', function () {
    $component = assignmentForm($this)->set('section_id', (string) $this->section->id)->set('title', 'Baru')->call('save');

    $assignment = Assignment::firstOrFail();

    $component->assertRedirect(route('assignments.edit', ['course' => $this->course, 'assignment' => $assignment]));
});

it('allows an assignment without a due date', function () {
    assignmentForm($this)->set('section_id', (string) $this->section->id)->set('title', 'Tanpa tenggat')->call('save')->assertHasNoErrors();

    expect(Assignment::firstOrFail()->due_at)->toBeNull();
});

it('validates the form', function (array $input, string $field) {
    $component = assignmentForm($this)->set('section_id', (string) $this->section->id)->set('title', 'Ok');

    foreach ($input as $key => $value) {
        $component->set($key, $value);
    }

    $component->call('save')->assertHasErrors($field);

    expect(Assignment::count())->toBe(0);
})->with([
    'title required' => [['title' => ''], 'title'],
    'section required' => [['section_id' => ''], 'section_id'],
    'section of another course' => [['section_id' => '999999'], 'section_id'],
    'category of another course' => [['grade_category_id' => '999999'], 'grade_category_id'],
    'max score zero' => [['max_score' => '0'], 'max_score'],
    'max score too high' => [['max_score' => '5000'], 'max_score'],
    'max score text' => [['max_score' => 'abc'], 'max_score'],
    'bad date format' => [['due_at' => '20/12/2026'], 'due_at'],
]);

it('rejects a section from another course', function () {
    $foreign = makeSection(makeCourse(), 'Asing');

    assignmentForm($this)->set('section_id', (string) $foreign->id)->set('title', 'X')->call('save')->assertHasErrors('section_id');
});

it('forbids students and other teachers from creating assignments', function (Role $role) {
    $user = makeUser($role);

    if ($role === Role::Student) {
        enroll($this->course, $user);
    }

    $this->actingAs($user)->get("/kelas/{$this->course->id}/tugas/buat")->assertForbidden();
})->with([Role::Student, Role::Teacher, Role::Principal]);

it('lets an admin create assignments in any course', function () {
    $this->actingAs(makeUser(Role::Admin))->get("/kelas/{$this->course->id}/tugas/buat")->assertOk();
});

it('updates an assignment and shows existing values', function () {
    $assignment = makeAssignment($this->course, ['title' => 'Lama', 'due_at' => '2026-12-01 10:30:00', 'max_score' => 50]);

    assignmentForm($this, ['assignment' => $assignment])
        ->assertSet('title', 'Lama')->assertSet('max_score', '50')->assertSet('due_at', '2026-12-01 10:30')
        ->set('title', 'Baru')->call('save')->assertHasNoErrors();

    expect($assignment->fresh()->title)->toBe('Baru');
});

it('returns 404 for an assignment of another course', function () {
    $foreign = makeAssignment(makeCourse());

    $this->actingAs($this->teacher)->get("/kelas/{$this->course->id}/tugas/{$foreign->id}/ubah")->assertNotFound();
    $this->actingAs($this->teacher)->get("/kelas/{$this->course->id}/tugas/{$foreign->id}")->assertNotFound();
});

it('soft deletes an assignment and keeps student submissions', function () {
    $assignment = makeAssignment($this->course);
    $student = makeUser(Role::Student);
    enroll($this->course, $student);
    $assignment->submissions()->create(['user_id' => $student->id, 'body' => '<p>x</p>', 'status' => 'submitted']);

    Livewire::actingAs($this->teacher)->test(Show::class, ['course' => $this->course, 'assignment' => $assignment])
        ->call('deleteAssignment')->assertRedirect(route('courses.show', $this->course));

    expect(Assignment::count())->toBe(0)->and(Assignment::withTrashed()->count())->toBe(1)
        ->and($assignment->submissions()->count())->toBe(1);

    $this->actingAs($this->teacher)->get("/kelas/{$this->course->id}/tugas/{$assignment->id}")->assertNotFound();
});

it('forbids students from deleting or editing', function () {
    $assignment = makeAssignment($this->course);
    $student = makeUser(Role::Student);
    enroll($this->course, $student);

    Livewire::actingAs($student)->test(Show::class, ['course' => $this->course, 'assignment' => $assignment])->call('deleteAssignment')->assertForbidden();
    $this->actingAs($student)->get("/kelas/{$this->course->id}/tugas/{$assignment->id}/ubah")->assertForbidden();

    expect(Assignment::count())->toBe(1);
});

it('adds attachments that are stored privately', function () {
    $assignment = makeAssignment($this->course);

    assignmentForm($this, ['assignment' => $assignment])
        ->set('upload', UploadedFile::fake()->createWithContent('Soal Latihan.pdf', pdfBytes()))->assertHasNoErrors()->assertSet('upload', null);

    $file = AssignmentFile::firstOrFail();

    expect($file->file_name)->toBe('Soal Latihan.pdf')
        ->and($file->mime_type)->toBe('application/pdf')
        ->and($file->file_path)->toStartWith("courses/{$this->course->id}/assignments/{$assignment->id}/");

    Storage::disk('local')->assertExists($file->file_path);
});

it('rejects dangerous attachments', function () {
    $assignment = makeAssignment($this->course);

    assignmentForm($this, ['assignment' => $assignment])
        ->set('upload', UploadedFile::fake()->createWithContent('virus.exe', 'MZ'.str_repeat("\0", 64)))->assertHasErrors('upload');
    assignmentForm($this, ['assignment' => $assignment])
        ->set('upload', UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;'))->assertHasErrors('upload');

    expect(AssignmentFile::count())->toBe(0);
});

it('limits the number of attachments', function () {
    $assignment = makeAssignment($this->course);

    foreach (range(1, 10) as $i) {
        $assignment->attachments()->create(['file_path' => "x/{$i}.pdf", 'file_name' => "{$i}.pdf"]);
    }

    assignmentForm($this, ['assignment' => $assignment])
        ->set('upload', UploadedFile::fake()->createWithContent('lagi.pdf', pdfBytes()))->assertHasErrors('upload');

    expect(AssignmentFile::count())->toBe(10);
});

it('removes an attachment and its file, but only from this assignment', function () {
    $assignment = makeAssignment($this->course);
    Storage::disk('local')->put('courses/1/a.pdf', 'x');
    $file = $assignment->attachments()->create(['file_path' => 'courses/1/a.pdf', 'file_name' => 'a.pdf']);
    $foreign = makeAssignment(makeCourse())->attachments()->create(['file_path' => 'courses/9/b.pdf', 'file_name' => 'b.pdf']);

    assignmentForm($this, ['assignment' => $assignment])->call('removeAttachment', $foreign->id)->assertNotFound();
    assignmentForm($this, ['assignment' => $assignment])->call('removeAttachment', $file->id);

    Storage::disk('local')->assertMissing('courses/1/a.pdf');

    expect(AssignmentFile::pluck('id')->all())->toBe([$foreign->id]);
});

it('cannot add attachments before the assignment is saved', function () {
    assignmentForm($this)->set('upload', UploadedFile::fake()->createWithContent('a.pdf', pdfBytes()))->assertNotFound();

    expect(AssignmentFile::count())->toBe(0);
});

it('purges assignment files when their section is deleted', function () {
    $assignment = makeAssignment($this->course);
    Storage::disk('local')->put('courses/1/a.pdf', 'x');
    Storage::disk('local')->put('courses/1/s.pdf', 'x');
    $assignment->attachments()->create(['file_path' => 'courses/1/a.pdf', 'file_name' => 'a.pdf']);
    $student = makeUser(Role::Student);
    $assignment->submissions()->create(['user_id' => $student->id, 'file_path' => 'courses/1/s.pdf', 'file_name' => 's.pdf', 'status' => 'submitted']);
    $assignment->delete(); // diarsipkan: berkas tetap sampai topiknya dihapus

    Storage::disk('local')->assertExists('courses/1/a.pdf');

    Livewire::actingAs($this->teacher)->test(App\Livewire\Courses\Show::class, ['course' => $this->course])->call('deleteSection', $this->section->id);

    Storage::disk('local')->assertMissing('courses/1/a.pdf');
    Storage::disk('local')->assertMissing('courses/1/s.pdf');
    expect(Assignment::withTrashed()->count())->toBe(0);
});
