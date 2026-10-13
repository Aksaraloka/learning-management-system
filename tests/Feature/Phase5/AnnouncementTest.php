<?php

use App\Enums\EnrollmentRole;
use App\Enums\Role;
use App\Livewire\Announcements\Index;
use App\Models\Announcement;
use Livewire\Livewire;

beforeEach(function () {
    $this->teacher = makeUser(Role::Teacher);
    $this->course = makeCourse($this->teacher);
    $this->student = makeUser(Role::Student);
    enroll($this->course, $this->student);
    $this->otherCourse = makeCourse(makeUser(Role::Teacher));
    $this->admin = makeUser(Role::Admin);
});

function postAnnouncement(object $test, object $user, string $course, string $title = 'Info Penting', string $body = '<p>Isi</p>')
{
    return Livewire::actingAs($user)->test(Index::class)->call('create')
        ->set('course_id', $course)->set('title', $title)->set('body', $body)->call('save');
}

it('lets a teacher post to their own course and notifies its members except the author', function () {
    postAnnouncement($this, $this->teacher, (string) $this->course->id, body: '<p>Halo <script>alert(1)</script>semua</p>')->assertHasNoErrors();

    $announcement = Announcement::firstOrFail();

    expect($announcement->course_id)->toBe($this->course->id)
        ->and($announcement->user_id)->toBe($this->teacher->id)
        ->and($announcement->body)->not->toContain('<script')
        ->and($this->student->notifications()->count())->toBe(1)
        ->and($this->student->notifications()->first()->data['kind'])->toBe('announcement')
        ->and($this->teacher->notifications()->count())->toBe(0);
});

it('does not let a teacher post to a course they do not teach', function () {
    postAnnouncement($this, $this->teacher, (string) $this->otherCourse->id)->assertHasErrors(['course_id']);

    expect(Announcement::count())->toBe(0);
});

it('does not let students post', function () {
    postAnnouncement($this, $this->student, (string) $this->course->id)->assertHasErrors(['course_id']);

    expect(Announcement::count())->toBe(0);
});

it('lets only admins post university-wide announcements to everyone', function () {
    postAnnouncement($this, $this->teacher, 'umum')->assertHasErrors(['course_id']);
    postAnnouncement($this, $this->admin, 'umum', 'Libur Nasional')->assertHasNoErrors();

    $announcement = Announcement::firstOrFail();

    expect($announcement->course_id)->toBeNull()
        ->and($this->student->notifications()->count())->toBe(1)
        ->and($this->teacher->notifications()->count())->toBe(1)
        ->and($this->admin->notifications()->count())->toBe(0);
});

it('validates title and body', function () {
    postAnnouncement($this, $this->teacher, (string) $this->course->id, '', '<p></p>')->assertHasErrors(['title', 'body']);
});

it('shows students only global and their own courses announcements, pinned first', function () {
    Announcement::create(['course_id' => $this->course->id, 'user_id' => $this->teacher->id, 'title' => 'Kelas Saya', 'body' => '<p>a</p>']);
    Announcement::create(['course_id' => $this->otherCourse->id, 'user_id' => $this->teacher->id, 'title' => 'Kelas Orang Lain', 'body' => '<p>b</p>']);
    Announcement::create(['course_id' => null, 'user_id' => $this->admin->id, 'title' => 'Umum Semua', 'body' => '<p>c</p>']);
    Announcement::create(['course_id' => $this->course->id, 'user_id' => $this->teacher->id, 'title' => 'Disematkan', 'body' => '<p>d</p>', 'is_pinned' => true]);

    Livewire::actingAs($this->student)->test(Index::class)
        ->assertSee('Kelas Saya')->assertSee('Umum Semua')->assertDontSee('Kelas Orang Lain')
        ->assertSeeInOrder(['Disematkan', 'Kelas Saya']);

    Livewire::actingAs($this->student)->test(Index::class)->set('scope', 'umum')->assertSee('Umum Semua')->assertDontSee('Kelas Saya');
});

it('lets the author or admin edit, pin and delete, and nobody else', function () {
    $announcement = Announcement::create(['course_id' => $this->course->id, 'user_id' => $this->teacher->id, 'title' => 'Asli', 'body' => '<p>a</p>']);
    $coTeacher = makeUser(Role::Teacher);
    enroll($this->course, $coTeacher, EnrollmentRole::Teacher);

    Livewire::actingAs($this->teacher)->test(Index::class)->call('edit', $announcement->id)->set('title', 'Diubah')->call('save')->assertHasNoErrors();
    expect($announcement->fresh()->title)->toBe('Diubah');

    Livewire::actingAs($this->admin)->test(Index::class)->call('togglePin', $announcement->id);
    expect($announcement->fresh()->is_pinned)->toBeTrue();

    Livewire::actingAs($this->student)->test(Index::class)->call('delete', $announcement->id)->assertForbidden();
    Livewire::actingAs($this->student)->test(Index::class)->call('edit', $announcement->id)->assertForbidden();
    Livewire::actingAs(makeUser(Role::Teacher))->test(Index::class)->call('delete', $announcement->id)->assertNotFound();

    Livewire::actingAs($coTeacher)->test(Index::class)->call('delete', $announcement->id);
    expect(Announcement::count())->toBe(0);
});

it('does not let a teacher edit a university-wide announcement', function () {
    $announcement = Announcement::create(['course_id' => null, 'user_id' => $this->admin->id, 'title' => 'Umum', 'body' => '<p>x</p>']);

    Livewire::actingAs($this->teacher)->test(Index::class)->call('edit', $announcement->id)->assertForbidden();
    Livewire::actingAs($this->teacher)->test(Index::class)->call('delete', $announcement->id)->assertForbidden();
});

it('requires login', function () {
    $this->get(route('announcements.index'))->assertRedirect(route('login'));
});
