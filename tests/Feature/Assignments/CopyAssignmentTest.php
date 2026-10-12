<?php

use App\Enums\Role;
use App\Livewire\Assignments\Show;
use App\Models\AcademicTerm;
use App\Models\Assignment;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');

    $this->teacher = makeUser(Role::Teacher);
    $this->source = makeCourse($this->teacher, ['name' => 'Matematika VII-A']);
    $this->source->gradeCategories()->create(['name' => 'Tugas', 'weight' => 40, 'position' => 1]);
    $this->assignment = makeAssignment($this->source, [
        'title' => 'Latihan Pecahan', 'instructions' => '<p>Soal</p>', 'due_at' => '2026-12-01 10:00:00', 'max_score' => 80, 'allow_late' => true,
        'grade_category_id' => $this->source->gradeCategories()->first()->id,
    ]);

    Storage::disk('local')->put('courses/1/soal.pdf', 'isi-soal');
    $this->assignment->attachments()->create(['file_path' => 'courses/1/soal.pdf', 'file_name' => 'soal.pdf', 'file_size' => 8, 'mime_type' => 'application/pdf']);

    $this->parallel = makeCourse($this->teacher, ['name' => 'Matematika VII-B', 'academic_term_id' => $this->source->academic_term_id]);
    $this->parallel->gradeCategories()->create(['name' => 'Tugas', 'weight' => 40, 'position' => 1]);
});

function copyPage($test, $user = null): Testable
{
    return Livewire::actingAs($user ?? $test->teacher)->test(Show::class, ['course' => $test->source, 'assignment' => $test->assignment]);
}

it('copies an assignment to a parallel course', function () {
    copyPage($this)->set('copyTo', [(string) $this->parallel->id])->call('copy')->assertHasNoErrors();

    $copy = Assignment::where('course_id', $this->parallel->id)->firstOrFail();

    expect($copy->title)->toBe('Latihan Pecahan')
        ->and($copy->instructions)->toBe('<p>Soal</p>')
        ->and($copy->max_score)->toBe(80)
        ->and($copy->allow_late)->toBeTrue()
        ->and($copy->due_at->equalTo($this->assignment->due_at))->toBeTrue()
        ->and($copy->grade_category_id)->toBe($this->parallel->gradeCategories()->first()->id)
        ->and($copy->section->course_id)->toBe($this->parallel->id)
        ->and(Assignment::count())->toBe(2);
});

it('creates a section in the target course when it has none', function () {
    expect($this->parallel->sections()->count())->toBe(0);

    copyPage($this)->set('copyTo', [(string) $this->parallel->id])->call('copy');

    expect($this->parallel->sections()->pluck('title')->all())->toBe(['Topik 1']);
});

it('puts the copy in the first existing section', function () {
    $first = makeSection($this->parallel, 'Bab Pertama');
    makeSection($this->parallel, 'Bab Kedua');

    copyPage($this)->set('copyTo', [(string) $this->parallel->id])->call('copy');

    expect(Assignment::where('course_id', $this->parallel->id)->firstOrFail()->section_id)->toBe($first->id);
});

it('duplicates attachments into new files', function () {
    copyPage($this)->set('copyTo', [(string) $this->parallel->id])->call('copy');

    $copy = Assignment::where('course_id', $this->parallel->id)->firstOrFail()->attachments()->firstOrFail();

    expect($copy->file_name)->toBe('soal.pdf')
        ->and($copy->file_path)->not->toBe('courses/1/soal.pdf')
        ->and($copy->file_path)->toStartWith("courses/{$this->parallel->id}/assignments/");

    Storage::disk('local')->assertExists($copy->file_path);
    Storage::disk('local')->assertExists('courses/1/soal.pdf');
    expect(Storage::disk('local')->get($copy->file_path))->toBe('isi-soal');
});

it('does not copy student submissions', function () {
    $student = makeUser(Role::Student);
    enroll($this->source, $student);
    $this->assignment->submissions()->create(['user_id' => $student->id, 'body' => '<p>x</p>', 'status' => 'graded', 'score' => 90]);

    copyPage($this)->set('copyTo', [(string) $this->parallel->id])->call('copy');

    expect(Assignment::where('course_id', $this->parallel->id)->firstOrFail()->submissions()->count())->toBe(0);
});

it('copies to several courses at once', function () {
    $third = makeCourse($this->teacher, ['academic_term_id' => $this->source->academic_term_id]);

    copyPage($this)->set('copyTo', [(string) $this->parallel->id, (string) $third->id])->call('copy');

    expect(Assignment::count())->toBe(3);
});

it('offers only courses of the same term that the teacher teaches', function () {
    $otherTerm = AcademicTerm::factory()->create(['name' => 'Genap']);
    $otherTermCourse = makeCourse($this->teacher, ['name' => 'Beda Semester', 'academic_term_id' => $otherTerm->id]);
    $notMine = makeCourse(null, ['name' => 'Milik Guru Lain', 'academic_term_id' => $this->source->academic_term_id]);

    copyPage($this)->assertSee('Matematika VII-B')->assertDontSee('Beda Semester')->assertDontSee('Milik Guru Lain');

    copyPage($this)->set('copyTo', [(string) $otherTermCourse->id, (string) $notMine->id])->call('copy')->assertStatus(422);

    expect(Assignment::count())->toBe(1);
});

it('lets an admin copy to any course in the same term', function () {
    $notMine = makeCourse(null, ['name' => 'Kelas Lain', 'academic_term_id' => $this->source->academic_term_id]);

    copyPage($this, makeUser(Role::Admin))->assertSee('Kelas Lain')->set('copyTo', [(string) $notMine->id])->call('copy');

    expect(Assignment::where('course_id', $notMine->id)->count())->toBe(1);
});

it('forbids students and unrelated teachers from copying', function () {
    $student = makeUser(Role::Student);
    enroll($this->source, $student);

    copyPage($this, $student)->set('copyTo', [(string) $this->parallel->id])->call('copy')->assertForbidden();
    copyPage($this, makeUser(Role::Teacher))->assertForbidden();

    expect(Assignment::count())->toBe(1);
});

it('never copies onto the same course', function () {
    copyPage($this)->set('copyTo', [(string) $this->source->id])->call('copy')->assertStatus(422);

    expect(Assignment::count())->toBe(1);
});
