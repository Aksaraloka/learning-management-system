<?php

use App\Enums\Role;
use App\Livewire\Assignments\Show;
use App\Models\AssignmentComment;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->teacher = makeUser(Role::Teacher, ['name' => 'Bu Guru']);
    $this->course = makeCourse($this->teacher);
    $this->assignment = makeAssignment($this->course);

    $this->ani = makeUser(Role::Student, ['name' => 'Ani']);
    $this->budi = makeUser(Role::Student, ['name' => 'Budi']);
    enroll($this->course, $this->ani);
    enroll($this->course, $this->budi);
});

function page($test, $user): Testable
{
    return Livewire::actingAs($user)->test(Show::class, ['course' => $test->course, 'assignment' => $test->assignment]);
}

it('lets members post public comments that the whole class sees', function () {
    page($this, $this->ani)->set('commentBody', 'Apakah boleh pakai kalkulator?')->call('postComment')->assertHasNoErrors();

    page($this, $this->budi)->assertSee('Apakah boleh pakai kalkulator?');
    page($this, $this->teacher)->assertSee('Apakah boleh pakai kalkulator?');

    $comment = AssignmentComment::firstOrFail();

    expect($comment->is_private)->toBeFalse()->and($comment->thread_user_id)->toBeNull();
});

it('keeps a private comment between the student and the teachers', function () {
    page($this, $this->ani)->set('commentBody', 'Saya sakit, boleh susulan?')->set('commentPrivate', true)->call('postComment')->assertHasNoErrors();

    $comment = AssignmentComment::firstOrFail();

    expect($comment->is_private)->toBeTrue()->and($comment->thread_user_id)->toBe($this->ani->id);

    page($this, $this->ani)->assertSee('Saya sakit, boleh susulan?');
    page($this, $this->budi)->assertDontSee('Saya sakit, boleh susulan?');
    // Pengajar membaca percakapan privat di layar penilaian, bukan di halaman tugas.
    page($this, $this->teacher)->assertDontSee('Saya sakit, boleh susulan?');
    expect($this->teacher->can('view', $comment))->toBeTrue()->and($this->budi->can('view', $comment))->toBeFalse()->and($this->ani->can('view', $comment))->toBeTrue();
});

it('never lets a teacher post a private comment from the assignment page', function () {
    page($this, $this->teacher)->set('commentBody', 'Pengumuman')->set('commentPrivate', true)->call('postComment');

    expect(AssignmentComment::firstOrFail()->is_private)->toBeFalse();
});

it('shows students the teacher reply in their private thread only', function () {
    $this->assignment->comments()->create(['user_id' => $this->teacher->id, 'body' => 'Balasan untuk Ani', 'is_private' => true, 'thread_user_id' => $this->ani->id]);

    page($this, $this->ani)->assertSee('Balasan untuk Ani');
    page($this, $this->budi)->assertDontSee('Balasan untuk Ani');
});

it('lets the author and the teaching staff delete comments', function () {
    $mine = $this->assignment->comments()->create(['user_id' => $this->ani->id, 'body' => 'Milik Ani', 'is_private' => false]);
    $theirs = $this->assignment->comments()->create(['user_id' => $this->budi->id, 'body' => 'Milik Budi', 'is_private' => false]);

    page($this, $this->ani)->call('deleteComment', $theirs->id)->assertForbidden();
    page($this, $this->ani)->call('deleteComment', $mine->id)->assertHasNoErrors();
    page($this, $this->teacher)->call('deleteComment', $theirs->id)->assertHasNoErrors();

    expect(AssignmentComment::count())->toBe(0);
});

it('validates comment text', function () {
    page($this, $this->ani)->set('commentBody', '')->call('postComment')->assertHasErrors('commentBody');
    page($this, $this->ani)->set('commentBody', str_repeat('a', 2001))->call('postComment')->assertHasErrors('commentBody');

    expect(AssignmentComment::count())->toBe(0);
});

it('escapes comment HTML', function () {
    page($this, $this->ani)->set('commentBody', '<script>alert(1)</script><b>tebal</b>')->call('postComment');

    page($this, $this->budi)->assertDontSee('<script>', false)->assertDontSee('<b>tebal</b>', false)->assertSee('&lt;script&gt;', false);
});

it('forbids outsiders from commenting or reading', function () {
    $outsider = makeUser(Role::Student);

    page($this, $outsider)->assertForbidden();
    expect($outsider->can('create', [AssignmentComment::class, $this->assignment]))->toBeFalse();
});

it('lets a principal read public comments', function () {
    $this->assignment->comments()->create(['user_id' => $this->ani->id, 'body' => 'Publik saja', 'is_private' => false]);
    $this->assignment->comments()->create(['user_id' => $this->ani->id, 'body' => 'Privat Ani', 'is_private' => true, 'thread_user_id' => $this->ani->id]);

    page($this, makeUser(Role::Principal))->assertSee('Publik saja')->assertDontSee('Privat Ani');
});
