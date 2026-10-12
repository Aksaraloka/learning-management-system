<?php

use App\Enums\Role;
use App\Livewire\Courses\Index;
use App\Livewire\Courses\Members;
use App\Models\Course;
use Livewire\Livewire;

it('lets a student join with the code, ignoring case and spaces', function () {
    $course = makeCourse();
    $student = makeUser(Role::Student);

    Livewire::actingAs($student)->test(Index::class)
        ->set('code', ' '.strtolower(substr($course->code, 0, 3)).'-'.strtolower(substr($course->code, 3)).' ')
        ->call('join')->assertHasNoErrors()->assertRedirect(route('courses.show', $course));

    expect($course->hasMember($student))->toBeTrue()
        ->and($course->students()->pluck('users.id')->all())->toBe([$student->id]);
});

it('rejects an unknown or empty code', function () {
    $student = makeUser(Role::Student);

    Livewire::actingAs($student)->test(Index::class)->set('code', 'ZZZZZZ')->call('join')->assertHasErrors('code');
    Livewire::actingAs($student)->test(Index::class)->set('code', '')->call('join')->assertHasErrors('code');
    Livewire::actingAs($student)->test(Index::class)->set('code', '----')->call('join')->assertHasErrors('code');
});

it('rejects joining twice', function () {
    $course = makeCourse();
    $student = makeUser(Role::Student);
    enroll($course, $student);

    Livewire::actingAs($student)->test(Index::class)->set('code', $course->code)->call('join')->assertHasErrors('code');

    expect($course->enrollments()->where('user_id', $student->id)->count())->toBe(1);
});

it('does not let teachers or admins join as students', function (Role $role) {
    $course = makeCourse();

    Livewire::actingAs(makeUser($role))->test(Index::class)->set('code', $course->code)->call('join')->assertForbidden();

    expect($course->students()->count())->toBe(0);
})->with([Role::Teacher, Role::Admin, Role::Principal]);

it('throttles repeated wrong codes', function () {
    $course = makeCourse();
    $student = makeUser(Role::Student);

    foreach (range(1, 10) as $_) {
        Livewire::actingAs($student)->test(Index::class)->set('code', 'ZZZZZZ')->call('join');
    }

    Livewire::actingAs($student)->test(Index::class)->set('code', $course->code)->call('join')->assertHasErrors('code');

    expect($course->hasMember($student))->toBeFalse();
});

it('enrolls many students at once and reports problems', function () {
    $teacher = makeUser(Role::Teacher);
    $course = makeCourse($teacher);
    $a = makeUser(Role::Student, ['identity_number' => '1001', 'email' => 'a@x.test']);
    $b = makeUser(Role::Student, ['identity_number' => '1002']);
    $already = makeUser(Role::Student, ['identity_number' => '1003']);
    $notStudent = makeUser(Role::Teacher, ['identity_number' => '1004']);
    enroll($course, $already);

    $component = Livewire::actingAs($teacher)->test(Members::class, ['course' => $course])
        ->set('role', 'student')
        ->set('identifiers', "1001\n1002, 1003;1004\nA@X.test\n9999\n")
        ->call('add')->assertHasNoErrors();

    $result = $component->get('result');

    expect($result['added'])->toBe(2)
        ->and($result['missing'])->toBe(['9999'])
        ->and($result['ineligible'])->toBe(['1004'])
        ->and($result['skipped'])->toEqualCanonicalizing(['1003', 'A@X.test'])
        ->and($course->students()->count())->toBe(3);

    $component->assertSet('identifiers', '');
});

it('enrolls teachers only if they have the teacher role', function () {
    $admin = makeUser(Role::Admin);
    $course = makeCourse();
    makeUser(Role::Teacher, ['identity_number' => 'T1']);
    makeUser(Role::Student, ['identity_number' => 'S1']);

    $result = Livewire::actingAs($admin)->test(Members::class, ['course' => $course])
        ->set('role', 'teacher')->set('identifiers', "T1\nS1")->call('add')->get('result');

    expect($result['added'])->toBe(1)->and($result['ineligible'])->toBe(['S1'])->and($course->teachers()->count())->toBe(2);
});

it('removes a member', function () {
    $teacher = makeUser(Role::Teacher);
    $course = makeCourse($teacher);
    $student = makeUser(Role::Student);
    enroll($course, $student);

    Livewire::actingAs($teacher)->test(Members::class, ['course' => $course])->call('remove', $student->id);

    expect($course->hasMember($student))->toBeFalse();
});

it('regenerates the join code so the old one stops working', function () {
    $teacher = makeUser(Role::Teacher);
    $course = makeCourse($teacher);
    $old = $course->code;

    Livewire::actingAs($teacher)->test(Members::class, ['course' => $course])->call('regenerateCode');

    expect($course->fresh()->code)->not->toBe($old);

    Livewire::actingAs(makeUser(Role::Student))->test(Index::class)->set('code', $old)->call('join')->assertHasErrors('code');
});

it('hides the roster from students and unrelated teachers', function () {
    $course = makeCourse();
    $student = makeUser(Role::Student);
    enroll($course, $student);

    $this->actingAs($student)->get("/kelas/{$course->id}/anggota")->assertForbidden();
    $this->actingAs(makeUser(Role::Teacher))->get("/kelas/{$course->id}/anggota")->assertForbidden();
    $this->actingAs(makeUser(Role::Admin))->get("/kelas/{$course->id}/anggota")->assertOk();
});

it('lets a principal view the roster but not change it', function () {
    $course = makeCourse();
    $student = makeUser(Role::Student, ['identity_number' => '7777']);
    enroll($course, $student);
    $principal = makeUser(Role::Principal);

    Livewire::actingAs($principal)->test(Members::class, ['course' => $course])
        ->assertOk()->assertSee($student->name)
        ->call('remove', $student->id)->assertForbidden();

    expect($course->hasMember($student))->toBeTrue();
});

it('forbids an unrelated teacher from managing members', function () {
    $course = makeCourse();
    $student = makeUser(Role::Student);
    enroll($course, $student);

    Livewire::actingAs(makeUser(Role::Teacher))->test(Members::class, ['course' => $course])->assertForbidden();
});

it('treats codes case-insensitively and strips punctuation', function () {
    expect(Course::normalizeCode(' ab-c d_12 '))->toBe('ABCD12');
});
