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

it('seeds one university with a lecturer, a head of study program and 30 students', function () {
    expect(User::count())->toBe(35)
        ->and(User::role(Role::Student->value)->count())->toBe(30)
        ->and(User::role(Role::Teacher->value)->count())->toBe(3)
        ->and(User::where('email', 'kaprodi@aksaraloka.test')->firstOrFail()->hasAppRole(Role::Principal))->toBeTrue()
        ->and(User::where('email', 'dosen1@aksaraloka.test')->firstOrFail()->hasAppRole(Role::Teacher, Role::Homeroom))->toBeTrue()
        ->and(User::where('identity_number', '2260001')->exists())->toBeTrue();
});

it('seeds twelve courses with credits, meetings and materials', function () {
    $this->seed(CourseSeeder::class);

    expect(Course::count())->toBe(12)
        ->and(Course::whereNull('credits')->count())->toBe(0)
        ->and(Course::where('name', 'Pemrograman Web')->firstOrFail()->credits)->toBe(4)
        ->and(Section::count())->toBe(37)
        ->and(Section::where('title', 'like', 'Pertemuan 1:%')->count())->toBe(12)
        ->and(Material::where('type', 'file')->count())->toBe(37)
        ->and(Material::where('type', 'video')->count())->toBe(12);

    Material::where('type', 'file')->each(fn ($m) => Storage::disk('local')->assertExists($m->file_path));

    $demo = User::where('email', 'mahasiswa1@aksaraloka.test')->firstOrFail();

    expect(Course::visibleTo($demo)->count())->toBe(12);
});

it('lets the demo lecturer manage and the demo student read the seeded courses', function () {
    $this->seed(CourseSeeder::class);

    $teacher = User::where('email', 'dosen1@aksaraloka.test')->firstOrFail();
    $student = User::where('email', 'mahasiswa1@aksaraloka.test')->firstOrFail();
    $course = Course::where('name', 'Algoritma dan Pemrograman')->firstOrFail();

    Livewire::actingAs($teacher)->test(Show::class, ['course' => $course])->assertSee('Pengantar Algoritma')->assertSee($course->code);
    Livewire::actingAs($student)->test(Show::class, ['course' => $course])->assertSee('Pengantar Algoritma')->assertDontSee($course->code);
});

it('produces valid PDFs for the demo files', function () {
    $this->seed(CourseSeeder::class);

    $bytes = Storage::disk('local')->get(Material::where('type', 'file')->firstOrFail()->file_path);

    expect($bytes)->toStartWith('%PDF-1.4')->and($bytes)->toContain('xref')->and($bytes)->toEndWith("%%EOF\n");
});

it('seeds demo assignments with a mix of statuses', function () {
    $this->seed(CourseSeeder::class);
    $this->seed(AssignmentSeeder::class);

    expect(Assignment::count())->toBe(24);

    $statuses = Submission::query()->pluck('status')->map->value->unique()->sort()->values()->all();

    expect($statuses)->toBe(['graded', 'late', 'returned', 'submitted']);

    $demo = User::where('email', 'mahasiswa1@aksaraloka.test')->firstOrFail();
    $open = Assignment::where('title', 'like', 'Latihan 1%')->firstOrFail();

    // Akun demo mahasiswa1 belum mengumpulkan tugas terbuka, jadi bisa langsung mencoba.
    expect(Submission::where('assignment_id', $open->id)->where('user_id', $demo->id)->exists())->toBeFalse();
});

it('lets one course have several lecturers and a lecturer teach several courses', function () {
    $this->seed(CourseSeeder::class);

    $rpl = Course::where('name', 'Rekayasa Perangkat Lunak')->firstOrFail();
    $single = Course::where('name', 'Jaringan Komputer')->firstOrFail();

    expect($rpl->teachers()->count())->toBe(3)
        ->and($single->teachers()->count())->toBe(1)
        ->and(Course::all()->filter(fn ($c) => $c->teachers()->count() > 1)->count())->toBeGreaterThan(6);

    // Dosen yang bukan pengampu utama tetap dapat mengelola mata kuliahnya.
    $coTeacher = User::where('email', 'dosen3@aksaraloka.test')->firstOrFail();

    expect($coTeacher->can('update', $rpl))->toBeTrue();
});
