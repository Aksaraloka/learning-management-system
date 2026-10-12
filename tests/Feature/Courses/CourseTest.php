<?php

use App\Enums\EnrollmentRole;
use App\Enums\Role;
use App\Livewire\Courses\Form;
use App\Livewire\Courses\Index;
use App\Livewire\Courses\Show;
use App\Models\AcademicTerm;
use App\Models\Course;
use Livewire\Livewire;

beforeEach(function () {
    $this->term = AcademicTerm::factory()->create();
});

it('lets a teacher create a course and enrolls them as its teacher', function () {
    $teacher = makeUser(Role::Teacher);

    Livewire::actingAs($teacher)->test(Form::class)
        ->set('academic_term_id', (string) $this->term->id)->set('name', 'Kalkulus I')->set('credits', '3')->set('description', 'Limit dan turunan')
        ->call('save')->assertHasNoErrors();

    $course = Course::firstOrFail();

    expect($course->name)->toBe('Kalkulus I')
        ->and($course->code)->toMatch('/^[A-HJKMNP-Z2-9]{6}$/')
        ->and($course->isTaughtBy($teacher))->toBeTrue()
        ->and($course->credits)->toBe(3);
});

it('requires an admin to choose the teacher', function () {
    $admin = makeUser(Role::Admin);
    $teacher = makeUser(Role::Teacher);

    Livewire::actingAs($admin)->test(Form::class)
        ->set('academic_term_id', (string) $this->term->id)->set('name', 'Statistika')->set('credits', '2')
        ->call('save')->assertHasErrors('teacher_id');

    Livewire::actingAs($admin)->test(Form::class)
        ->set('academic_term_id', (string) $this->term->id)->set('name', 'Statistika')->set('credits', '2')->set('teacher_id', (string) $teacher->id)
        ->call('save')->assertHasNoErrors();

    expect(Course::firstOrFail()->isTaughtBy($teacher))->toBeTrue();
});

it('refuses a teacher id that is not a teacher', function () {
    $admin = makeUser(Role::Admin);
    $student = makeUser(Role::Student);

    Livewire::actingAs($admin)->test(Form::class)
        ->set('academic_term_id', (string) $this->term->id)->set('name', 'Statistika')->set('credits', '2')->set('teacher_id', (string) $student->id)
        ->call('save')->assertStatus(422);

    expect(Course::count())->toBe(0);
});

it('validates the course form', function () {
    Livewire::actingAs(makeUser(Role::Teacher))->test(Form::class)->call('save')->assertHasErrors(['academic_term_id', 'name', 'credits']);
});

it('requires credits (SKS) between 1 and 24', function (string $credits, bool $valid) {
    $component = Livewire::actingAs(makeUser(Role::Teacher))->test(Form::class)
        ->set('academic_term_id', (string) $this->term->id)->set('name', 'Basis Data')->set('credits', $credits)->call('save');

    $valid ? $component->assertHasNoErrors() : $component->assertHasErrors('credits');

    expect(Course::count())->toBe($valid ? 1 : 0);
})->with([['', false], ['0', false], ['25', false], ['abc', false], ['1', true], ['24', true], ['3', true]]);

it('forbids students and principals from creating courses', function (Role $role) {
    $this->actingAs(makeUser($role))->get('/kelas/buat')->assertForbidden();
})->with([Role::Student, Role::Principal, Role::Parent]);

it('only lets the teaching teacher or an admin edit a course', function () {
    $course = makeCourse($owner = makeUser(Role::Teacher));

    $this->actingAs($owner)->get("/kelas/{$course->id}/ubah")->assertOk();
    $this->actingAs(makeUser(Role::Admin))->get("/kelas/{$course->id}/ubah")->assertOk();
    $this->actingAs(makeUser(Role::Teacher))->get("/kelas/{$course->id}/ubah")->assertForbidden();
    $this->actingAs(makeUser(Role::Principal))->get("/kelas/{$course->id}/ubah")->assertForbidden();

    $student = makeUser(Role::Student);
    enroll($course, $student);
    $this->actingAs($student)->get("/kelas/{$course->id}/ubah")->assertForbidden();
});

it('updates a course', function () {
    $course = makeCourse($teacher = makeUser(Role::Teacher), ['name' => 'Lama']);

    Livewire::actingAs($teacher)->test(Form::class, ['course' => $course])
        ->assertSet('name', 'Lama')->set('name', 'Baru')->call('save')->assertHasNoErrors();

    expect($course->fresh()->name)->toBe('Baru');
});

it('shows each user only the courses they belong to', function () {
    $teacher = makeUser(Role::Teacher);
    $mine = makeCourse($teacher, ['name' => 'Kelas Saya']);
    makeCourse(null, ['name' => 'Kelas Lain']);
    $student = makeUser(Role::Student);
    enroll($mine, $student);

    Livewire::actingAs($student)->test(Index::class)->assertSee('Kelas Saya')->assertDontSee('Kelas Lain');
    Livewire::actingAs($teacher)->test(Index::class)->assertSee('Kelas Saya')->assertDontSee('Kelas Lain');
    Livewire::actingAs(makeUser(Role::Admin))->test(Index::class)->assertSee('Kelas Saya')->assertSee('Kelas Lain');
    Livewire::actingAs(makeUser(Role::Principal))->test(Index::class)->assertSee('Kelas Saya')->assertSee('Kelas Lain');
});

it('lets only members, admins and principals open a course', function () {
    $course = makeCourse();
    $member = makeUser(Role::Student);
    enroll($course, $member);

    $this->actingAs($member)->get("/kelas/{$course->id}")->assertOk()->assertSee($course->name);
    $this->actingAs(makeUser(Role::Admin))->get("/kelas/{$course->id}")->assertOk();
    $this->actingAs(makeUser(Role::Principal))->get("/kelas/{$course->id}")->assertOk();
    $this->actingAs(makeUser(Role::Student))->get("/kelas/{$course->id}")->assertForbidden();
    $this->actingAs(makeUser(Role::Teacher))->get("/kelas/{$course->id}")->assertForbidden();
});

it('hides the join code from students', function () {
    $course = makeCourse();
    $student = makeUser(Role::Student);
    enroll($course, $student);

    Livewire::actingAs($student)->test(Show::class, ['course' => $course])->assertDontSee($course->code);
    Livewire::actingAs(makeUser(Role::Admin))->test(Show::class, ['course' => $course])->assertSee($course->code);
});

it('requires login for course pages', function () {
    $course = makeCourse();

    $this->get('/kelas')->assertRedirect(route('login'));
    $this->get("/kelas/{$course->id}")->assertRedirect(route('login'));
});

it('soft deletes a course and hides it', function () {
    $course = makeCourse($teacher = makeUser(Role::Teacher));

    Livewire::actingAs($teacher)->test(Show::class, ['course' => $course])->call('deleteCourse')->assertRedirect(route('courses.index'));

    expect(Course::count())->toBe(0)->and(Course::withTrashed()->count())->toBe(1);

    $this->actingAs($teacher)->get("/kelas/{$course->id}")->assertNotFound();
});

it('forbids other teachers from deleting a course', function () {
    $course = makeCourse();

    Livewire::actingAs(makeUser(Role::Teacher))->test(Show::class, ['course' => $course])->assertForbidden();
});

it('searches and filters the course list', function () {
    $teacher = makeUser(Role::Teacher);
    $other = AcademicTerm::factory()->create(['name' => 'Genap']);
    makeCourse($teacher, ['name' => 'Aljabar Linear']);
    makeCourse($teacher, ['name' => 'Fisika Dasar', 'academic_term_id' => $other->id]);

    Livewire::actingAs($teacher)->test(Index::class)
        ->set('search', 'Aljabar')->assertSee('Aljabar Linear')->assertDontSee('Fisika Dasar')
        ->set('search', '')->set('term', (string) $other->id)->assertSee('Fisika Dasar')->assertDontSee('Aljabar Linear');
});

it('does not offer locked terms when creating a course', function () {
    AcademicTerm::factory()->locked()->create(['name' => 'Term Terkunci']);

    Livewire::actingAs(makeUser(Role::Teacher))->test(Form::class)->assertDontSee('Term Terkunci');
});

it('uses distinct join codes', function () {
    $codes = collect(range(1, 30))->map(fn () => Course::generateCode());

    expect($codes->unique())->toHaveCount(30)->and($codes->every(fn ($c) => strlen($c) === 6))->toBeTrue();
});

it('records the enrollment role as an enum', function () {
    $course = makeCourse($teacher = makeUser(Role::Teacher));

    expect($course->enrollments()->firstOrFail()->role)->toBe(EnrollmentRole::Teacher)
        ->and($course->teachers->pluck('id')->all())->toBe([$teacher->id]);
});
