<?php

use App\Actions\Quizzes\ManageQuestions;
use App\Enums\EnrollmentRole;
use App\Enums\QuestionType;
use App\Enums\Role;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Material;
use App\Models\Question;
use App\Models\Quiz;
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

/**
 * Kuis terbit yang sudah dibuka (tanpa batas waktu) di kelas; topik dibuat bila belum ada.
 *
 * @param  array<string, mixed>  $attributes
 */
function makeQuiz(Course $course, array $attributes = []): Quiz
{
    $section = $course->sections()->first() ?? makeSection($course);

    return Quiz::create($attributes + [
        'course_id' => $course->id,
        'section_id' => $section->id,
        'title' => 'Kuis 1',
        'is_published' => true,
        'max_attempts' => 1,
        'position' => 1,
    ]);
}

/**
 * Soal pada kuis lewat action yang sama dengan form. Bawaan per tipe: PG (kunci opsi ke-2), B/S (benar), isian
 * (jawaban "jakarta"), esai.
 *
 * @param  array<string, mixed>  $data
 */
function addQuestion(Quiz $quiz, QuestionType $type = QuestionType::MultipleChoice, array $data = []): Question
{
    $defaults = match ($type) {
        QuestionType::MultipleChoice => ['options' => ['A', 'B', 'C', 'D'], 'correct_index' => 1],
        QuestionType::TrueFalse => ['true_false' => true],
        QuestionType::ShortAnswer => ['accepted' => ['jakarta'], 'case_sensitive' => false],
        QuestionType::Essay => ['rubric' => 'Rubrik rahasia'],
    };

    return app(ManageQuestions::class)->save($quiz, $data + $defaults + ['type' => $type, 'body' => '<p>Soal</p>', 'points' => 2]);
}

/**
 * Tugas di kelas (membuat topik bila belum ada). Tenggat bawaan 3 hari lagi.
 *
 * @param  array<string, mixed>  $attributes
 */
function makeAssignment(Course $course, array $attributes = []): Assignment
{
    $section = $course->sections()->first() ?? makeSection($course);

    return Assignment::create($attributes + [
        'course_id' => $course->id,
        'section_id' => $section->id,
        'title' => 'Latihan',
        'instructions' => '<p>Kerjakan</p>',
        'due_at' => now()->addDays(3),
        'max_score' => 100,
        'allow_late' => false,
        'position' => 1,
    ]);
}
