<?php

use App\Enums\EnrollmentRole;
use App\Enums\Role;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Material;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Pengguna dengan peran tertentu.
 *
 * @param  array<string, mixed>  $attributes
 */
function makeUser(Role|array $roles = Role::Student, array $attributes = []): User
{
    return User::factory()->role(...(is_array($roles) ? $roles : [$roles]))->create($attributes);
}

/**
 * Kelas dengan satu pengajar (dibuat bila tidak diberikan).
 *
 * @param  array<string, mixed>  $attributes
 */
function makeCourse(?User $teacher = null, array $attributes = []): Course
{
    $course = Course::factory()->create($attributes);

    Enrollment::create([
        'course_id' => $course->id,
        'user_id' => ($teacher ?? makeUser(Role::Teacher))->id,
        'role' => EnrollmentRole::Teacher,
    ]);

    return $course;
}

function enroll(Course $course, User $user, EnrollmentRole $role = EnrollmentRole::Student): void
{
    Enrollment::create(['course_id' => $course->id, 'user_id' => $user->id, 'role' => $role]);
}

function makeSection(Course $course, string $title = 'Bab 1'): Section
{
    return $course->sections()->create(['title' => $title, 'position' => ($course->sections()->max('position') ?? 0) + 1]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function makeMaterial(Section $section, array $attributes = []): Material
{
    return $section->materials()->create($attributes + [
        'type' => 'text', 'title' => 'Materi', 'body' => '<p>Isi</p>', 'position' => ($section->materials()->max('position') ?? 0) + 1,
    ]);
}

/**
 * Isi PDF minimal yang dikenali sebagai application/pdf.
 */
function pdfBytes(): string
{
    return "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 100 100]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
}
