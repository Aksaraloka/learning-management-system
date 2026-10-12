<?php

use App\Enums\Role;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    $this->course = makeCourse($this->teacher = makeUser(Role::Teacher));
    $section = makeSection($this->course);

    Storage::disk('local')->put('courses/1/slide.pdf', pdfBytes());
    Storage::disk('local')->put('courses/1/data.zip', 'PK');

    $this->pdf = makeMaterial($section, ['type' => 'file', 'file_path' => 'courses/1/slide.pdf', 'file_name' => 'Slide Bab 1.pdf', 'mime_type' => 'application/pdf']);
    $this->zip = makeMaterial($section, ['type' => 'file', 'file_path' => 'courses/1/data.zip', 'file_name' => 'data.zip', 'mime_type' => 'application/zip']);
    $this->student = makeUser(Role::Student);
    enroll($this->course, $this->student);
});

it('serves a PDF inline to course members', function () {
    $response = $this->actingAs($this->student)->get(route('materials.file', $this->pdf))->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/pdf')
        ->and($response->headers->get('Content-Disposition'))->toStartWith('inline')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Content-Security-Policy'))->toContain('sandbox');
});

it('forces a download when asked or when the type cannot be previewed', function () {
    $download = $this->actingAs($this->student)->get(route('materials.file', $this->pdf).'?download=1')->assertOk();
    $zip = $this->actingAs($this->student)->get(route('materials.file', $this->zip))->assertOk();

    expect($download->headers->get('Content-Disposition'))->toStartWith('attachment')
        ->and($download->headers->get('Content-Disposition'))->toContain('Slide Bab 1.pdf')
        ->and($zip->headers->get('Content-Disposition'))->toStartWith('attachment');
});

it('serves files to teachers, admins and principals', function (Role $role) {
    $user = $role === Role::Teacher ? $this->teacher : makeUser($role);

    $this->actingAs($user)->get(route('materials.file', $this->pdf))->assertOk();
})->with([Role::Teacher, Role::Admin, Role::Principal]);

it('forbids non-members and guests', function () {
    $this->actingAs(makeUser(Role::Student))->get(route('materials.file', $this->pdf))->assertForbidden();
    $this->actingAs(makeUser(Role::Teacher))->get(route('materials.file', $this->pdf))->assertForbidden();

    auth()->logout();
    $this->get(route('materials.file', $this->pdf))->assertRedirect(route('login'));
});

it('returns 404 when the file is missing or the material is not a file', function () {
    Storage::disk('local')->delete('courses/1/slide.pdf');
    $text = makeMaterial($this->pdf->section);

    $this->actingAs($this->student)->get(route('materials.file', $this->pdf))->assertNotFound();
    $this->actingAs($this->student)->get(route('materials.file', $text))->assertNotFound();
});

it('does not expose the storage path', function () {
    // Laravel menyajikan disk lokal hanya lewat URL bertanda tangan; tanpa tanda tangan ditolak.
    $this->actingAs($this->student)->get('/storage/courses/1/slide.pdf')->assertForbidden();
    $this->actingAs($this->student)->get('/courses/1/slide.pdf')->assertNotFound();
});
