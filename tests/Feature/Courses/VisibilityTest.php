<?php

use App\Enums\Role;
use App\Enums\SubmissionStatus;
use App\Livewire\Assignments\Index as AssignmentsIndex;
use App\Livewire\Courses\Show;
use App\Models\Submission;
use App\Support\CourseProgress;
use App\Support\Gradebook;
use Livewire\Livewire;

beforeEach(function () {
    $this->teacher = makeUser(Role::Teacher);
    $this->course = makeCourse($this->teacher);
    $this->section = makeSection($this->course);
    $this->student = makeUser(Role::Student);
    enroll($this->course, $this->student);
});

it('lets the teacher hide and show a material, an assignment and a quiz', function () {
    $material = makeMaterial($this->section);
    $assignment = makeAssignment($this->course);
    $quiz = makeQuiz($this->course);
    $page = fn () => Livewire::actingAs($this->teacher)->test(Show::class, ['course' => $this->course]);

    $page()->call('toggleVisibility', 'material', $material->id)->call('toggleVisibility', 'assignment', $assignment->id)->call('toggleVisibility', 'quiz', $quiz->id);

    expect($material->fresh()->is_hidden)->toBeTrue()->and($assignment->fresh()->is_hidden)->toBeTrue()->and($quiz->fresh()->is_published)->toBeFalse();

    $page()->call('toggleVisibility', 'material', $material->id)->call('toggleVisibility', 'assignment', $assignment->id)->call('toggleVisibility', 'quiz', $quiz->id);

    expect($material->fresh()->is_hidden)->toBeFalse()->and($assignment->fresh()->is_hidden)->toBeFalse()->and($quiz->fresh()->is_published)->toBeTrue();
});

it('hides hidden items from students on the course page but still shows them to the teacher with a marker', function () {
    $material = makeMaterial($this->section, ['title' => 'Materi Rahasia']);
    $assignment = makeAssignment($this->course, ['title' => 'Tugas Rahasia']);
    $material->update(['is_hidden' => true]);
    $assignment->update(['is_hidden' => true]);

    $this->actingAs($this->student)->get(route('courses.show', $this->course))->assertOk()->assertDontSee('Materi Rahasia')->assertDontSee('Tugas Rahasia');
    $this->actingAs($this->teacher)->get(route('courses.show', $this->course))->assertOk()->assertSee('Materi Rahasia')->assertSee('Tugas Rahasia')->assertSee('Disembunyikan dari mahasiswa');
    $this->actingAs(makeUser(Role::Principal))->get(route('courses.show', $this->course))->assertSee('Materi Rahasia');
});

it('blocks students from opening hidden materials, files and assignments directly', function () {
    $material = makeMaterial($this->section);
    $assignment = makeAssignment($this->course);
    $material->update(['is_hidden' => true]);
    $assignment->update(['is_hidden' => true]);

    $this->actingAs($this->student)->get(route('materials.show', ['course' => $this->course, 'material' => $material]))->assertForbidden();
    $this->actingAs($this->student)->get(route('materials.file', $material))->assertForbidden();
    $this->actingAs($this->student)->get(route('assignments.show', ['course' => $this->course, 'assignment' => $assignment]))->assertForbidden();

    $this->actingAs($this->teacher)->get(route('materials.show', ['course' => $this->course, 'material' => $material]))->assertOk();
    $this->actingAs($this->teacher)->get(route('assignments.show', ['course' => $this->course, 'assignment' => $assignment]))->assertOk();

    $material->update(['is_hidden' => false]);
    $this->actingAs($this->student)->get(route('materials.show', ['course' => $this->course, 'material' => $material]))->assertOk();
});

it('removes hidden assignments from the student assignment list and dashboard deadlines', function () {
    $assignment = makeAssignment($this->course, ['title' => 'Tugas Disembunyikan', 'due_at' => now()->addDay()]);

    Livewire::actingAs($this->student)->test(AssignmentsIndex::class)->assertSee('Tugas Disembunyikan');
    $this->actingAs($this->student)->get(route('home'))->assertSee('Tugas Disembunyikan');

    $assignment->update(['is_hidden' => true]);

    Livewire::actingAs($this->student)->test(AssignmentsIndex::class)->assertDontSee('Tugas Disembunyikan');
    $this->actingAs($this->student)->get(route('home'))->assertDontSee('Tugas Disembunyikan');
    Livewire::actingAs($this->teacher)->test(AssignmentsIndex::class)->assertSee('Tugas Disembunyikan');
});

it('does not count hidden assignments in the gradebook, progress or reminders', function () {
    $category = $this->course->gradeCategories()->create(['name' => 'Tugas', 'weight' => 100, 'position' => 1]);
    $assignment = makeAssignment($this->course, ['grade_category_id' => $category->id, 'due_at' => now()->subDay()]);
    Submission::create(['assignment_id' => $assignment->id, 'user_id' => $this->student->id, 'body' => 'x', 'status' => SubmissionStatus::Graded, 'score' => 80, 'graded_at' => now()]);

    expect(Gradebook::compute($this->course)['rows'][$this->student->id]['final'])->toBe(80.0)
        ->and(CourseProgress::forStudent($this->student, [$this->course->id])[$this->course->id]['total'])->toBe(1);

    $assignment->update(['is_hidden' => true]);

    expect(Gradebook::compute($this->course)['rows'][$this->student->id]['final'])->toBeNull()
        ->and(Gradebook::compute($this->course)['items'])->toBe([])
        ->and(CourseProgress::forStudent($this->student, [$this->course->id])[$this->course->id])->toBe(['done' => 0, 'total' => 0, 'percent' => 0]);

    $assignment->update(['due_at' => now()->addHours(5)]);
    $this->student->notifications()->delete();
    $this->artisan('reminders:due')->assertSuccessful();

    expect($this->student->notifications()->count())->toBe(0);
});

it('does not let students or other teachers toggle visibility, and ignores items of other courses', function () {
    $material = makeMaterial($this->section);
    $otherCourse = makeCourse(makeUser(Role::Teacher));
    $foreign = makeAssignment($otherCourse);

    Livewire::actingAs($this->student)->test(Show::class, ['course' => $this->course])->call('toggleVisibility', 'material', $material->id)->assertForbidden();
    Livewire::actingAs(makeUser(Role::Teacher))->test(Show::class, ['course' => $this->course])->assertForbidden();
    Livewire::actingAs($this->teacher)->test(Show::class, ['course' => $this->course])->call('toggleVisibility', 'assignment', $foreign->id)->assertNotFound();
    Livewire::actingAs($this->teacher)->test(Show::class, ['course' => $this->course])->call('toggleVisibility', 'bogus', 1)->assertNotFound();

    expect($material->fresh()->is_hidden)->toBeFalse()->and($foreign->fresh()->is_hidden)->toBeFalse();
});

it('shows the eye toggle only to managers', function () {
    makeMaterial($this->section);

    $this->actingAs($this->teacher)->get(route('courses.show', $this->course))->assertSee('Sembunyikan dari mahasiswa');
    $this->actingAs($this->student)->get(route('courses.show', $this->course))->assertDontSee('Sembunyikan dari mahasiswa');
});
