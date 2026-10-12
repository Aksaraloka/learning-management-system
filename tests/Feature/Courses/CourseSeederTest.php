<?php

use App\Enums\Role;
use App\Livewire\Courses\Show;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Material;
use App\Models\Section;
use App\Models\Submission;
use App\Models\User;
use Database\Seeders\AssignmentSeeder;
use Database\Seeders\CourseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(DemoSeeder::class);
});

it('seeds four school courses with sections and materials', function () {
    $this->seed(CourseSeeder::class);

    expect(Course::count())->toBe(4)
        ->and(Section::count())->toBe(11)
        ->and(Material::where('type', 'file')->count())->toBe(11)
        ->and(Material::where('type', 'video')->count())->toBe(4)
        ->and(Course::whereNotNull('credits')->count())->toBe(0);

    Material::where('type', 'file')->each(fn ($m) => Storage::disk('local')->assertExists($m->file_path));

    $demo = User::where('email', 'siswa1@aksaraloka.test')->firstOrFail();

    expect(Course::visibleTo($demo)->count())->toBe(4);
});

it('seeds campus courses with credits and meeting titles', function () {
    config(['institution.level' => 'campus']);

    $this->seed(CourseSeeder::class);

    expect(Course::whereNull('credits')->count())->toBe(0)
        ->and(Course::where('name', 'Pemrograman Web')->firstOrFail()->credits)->toBe(4)
        ->and(Section::where('title', 'like', 'Pertemuan 1:%')->count())->toBe(4);
});

it('lets the demo teacher manage and the demo student read the seeded courses', function () {
    $this->seed(CourseSeeder::class);

    $teacher = User::where('email', 'guru1@aksaraloka.test')->firstOrFail();
    $student = User::where('email', 'siswa1@aksaraloka.test')->firstOrFail();
    $course = Course::where('name', 'Matematika VII-A')->firstOrFail();

    Livewire::actingAs($teacher)->test(Show::class, ['course' => $course])->assertSee('Bilangan Bulat')->assertSee($course->code);
    Livewire::actingAs($student)->test(Show::class, ['course' => $course])->assertSee('Bilangan Bulat')->assertDontSee($course->code);

    expect($teacher->hasAppRole(Role::Teacher))->toBeTrue();
});

it('produces valid PDFs for the demo files', function () {
    $this->seed(CourseSeeder::class);

    $material = Material::where('type', 'file')->firstOrFail();
    $bytes = Storage::disk('local')->get($material->file_path);

    expect($bytes)->toStartWith('%PDF-1.4')->and($bytes)->toContain('xref')->and($bytes)->toEndWith("%%EOF\n");
});

it('seeds demo assignments with a mix of statuses', function () {
    $this->seed(CourseSeeder::class);
    $this->seed(AssignmentSeeder::class);

    expect(Assignment::count())->toBe(8);

    $statuses = Submission::query()->pluck('status')->map->value->unique()->sort()->values()->all();

    expect($statuses)->toBe(['graded', 'late', 'returned', 'submitted']);

    $demo = User::where('email', 'siswa1@aksaraloka.test')->firstOrFail();
    $open = Assignment::where('title', 'like', 'Latihan 1%')->firstOrFail();

    // Akun demo siswa1 belum mengumpulkan tugas terbuka, jadi bisa langsung mencoba.
    expect(Submission::where('assignment_id', $open->id)->where('user_id', $demo->id)->exists())->toBeFalse();
});
