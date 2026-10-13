<?php

use App\Enums\Role;
use App\Livewire\Courses\Form;
use App\Models\Course;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake(config('courses.disk'));
    $this->teacher = makeUser(Role::Teacher);
    $this->course = makeCourse($this->teacher, ['name' => 'Basis Data & SQL']);
});

it('falls back to a placehold.co placeholder named after the course', function () {
    $url = $this->course->bannerUrl();

    expect($url)->toStartWith('https://placehold.co/1200x400/')->toContain('text=Basis%20Data%20%26%20SQL');

    $this->actingAs($this->teacher)->get(route('courses.show', $this->course))->assertOk()->assertSee('https://placehold.co/1200x400/', false);
    $this->actingAs($this->teacher)->get(route('courses.index'))->assertOk()->assertSee('placehold.co', false);
});

it('uploads, serves and replaces a banner, deleting the old file', function () {
    $form = fn () => Livewire::actingAs($this->teacher)->test(Form::class, ['course' => $this->course->fresh()]);

    $form()->set('banner', UploadedFile::fake()->image('a.png', 1200, 400))->call('save')->assertHasNoErrors();
    $first = $this->course->fresh();

    expect($first->hasBanner())->toBeTrue()->and($first->bannerUrl())->toContain("/kelas/{$first->id}/banner");
    Storage::disk(config('courses.disk'))->assertExists($first->banner_path);

    $this->actingAs($this->teacher)->get(route('courses.banner', $first))->assertOk()->assertHeader('Content-Type', 'image/png');

    $form()->set('banner', UploadedFile::fake()->image('b.jpg', 1200, 400))->call('save')->assertHasNoErrors();

    Storage::disk(config('courses.disk'))->assertMissing($first->banner_path);
    Storage::disk(config('courses.disk'))->assertExists($this->course->fresh()->banner_path);
});

it('removes the banner and goes back to the placeholder', function () {
    Livewire::actingAs($this->teacher)->test(Form::class, ['course' => $this->course])->set('banner', UploadedFile::fake()->image('a.png'))->call('save');
    $path = $this->course->fresh()->banner_path;

    Livewire::actingAs($this->teacher)->test(Form::class, ['course' => $this->course->fresh()])->set('removeBanner', true)->call('save')->assertHasNoErrors();

    expect($this->course->fresh()->hasBanner())->toBeFalse()->and($this->course->fresh()->bannerUrl())->toContain('placehold.co');
    Storage::disk(config('courses.disk'))->assertMissing($path);
});

it('keeps the banner when the form is saved without touching it', function () {
    Livewire::actingAs($this->teacher)->test(Form::class, ['course' => $this->course])->set('banner', UploadedFile::fake()->image('a.png'))->call('save');
    $path = $this->course->fresh()->banner_path;

    Livewire::actingAs($this->teacher)->test(Form::class, ['course' => $this->course->fresh()])->set('name', 'Nama Baru')->call('save')->assertHasNoErrors();

    expect($this->course->fresh()->banner_path)->toBe($path);
});

it('rejects non-image banners', function () {
    Livewire::actingAs($this->teacher)->test(Form::class, ['course' => $this->course])
        ->set('banner', UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'))->call('save')->assertHasErrors(['banner']);

    expect($this->course->fresh()->hasBanner())->toBeFalse();
});

it('serves the banner only to course members, admin and principal', function () {
    Livewire::actingAs($this->teacher)->test(Form::class, ['course' => $this->course])->set('banner', UploadedFile::fake()->image('a.png'))->call('save');
    $url = route('courses.banner', $this->course);
    $student = makeUser(Role::Student);
    enroll($this->course, $student);

    $this->actingAs($student)->get($url)->assertOk();
    $this->actingAs(makeUser(Role::Admin))->get($url)->assertOk();
    $this->actingAs(makeUser(Role::Principal))->get($url)->assertOk();
    $this->actingAs(makeUser(Role::Student))->get($url)->assertForbidden();
    $this->actingAs(makeUser(Role::Teacher))->get($url)->assertForbidden();
});

it('returns 404 for a course without an uploaded banner', function () {
    $this->actingAs($this->teacher)->get(route('courses.banner', $this->course))->assertNotFound();
});

it('lets a new course be created with a banner', function () {
    $term = $this->course->term;

    Livewire::actingAs($this->teacher)->test(Form::class)
        ->set('academic_term_id', (string) $term->id)->set('name', 'Jaringan')->set('credits', '3')
        ->set('banner', UploadedFile::fake()->image('n.png'))->call('save')->assertHasNoErrors();

    expect(Course::where('name', 'Jaringan')->first()->hasBanner())->toBeTrue();
});
