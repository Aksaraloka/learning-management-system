<?php

namespace Database\Seeders;

use App\Enums\SubmissionStatus;
use App\Models\Assignment;
use App\Models\AssignmentComment;
use App\Models\Course;
use App\Models\Submission;
use App\Models\User;
use App\Support\SamplePdf;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Dua tugas per kelas demo: satu masih terbuka (mahasiswa1 belum mengumpulkan, bisa dicoba) dan satu sudah lewat tenggat
 * dengan campuran status (dinilai, menunggu nilai, terlambat, perlu revisi, belum mengumpulkan). Jalankan setelah CourseSeeder.
 */
class AssignmentSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Course::query()->with(['sections', 'gradeCategories'])->orderBy('id')->get() as $course) {
            $teacher = $course->teachers()->firstOrFail();
            $students = $course->students()->reorder('users.identity_number')->get();
            $section = $course->sections->first();
            $category = $course->gradeCategories->firstWhere('name', 'Tugas');

            if (! $section) {
                continue;
            }

            $open = Assignment::create([
                'course_id' => $course->id, 'section_id' => $section->id, 'grade_category_id' => $category?->id,
                'title' => 'Latihan 1',
                'instructions' => '<p>Kerjakan latihan berikut dan kumpulkan sebelum tenggat.</p><ol><li>Baca ringkasan materi.</li><li>Jawab soal 1-5.</li></ol><p>Rumus bantu: $a^2 + b^2 = c^2$</p>',
                'due_at' => now()->addDays(5)->setTime(23, 59), 'max_score' => 100, 'allow_late' => true, 'position' => 1,
            ]);

            $path = "courses/{$course->id}/assignments/{$open->id}/soal.pdf";
            $pdf = SamplePdf::make('Soal Latihan 1');
            Storage::disk(config('courses.disk'))->put($path, $pdf);
            $open->attachments()->create(['file_path' => $path, 'file_name' => 'soal-latihan-1.pdf', 'file_size' => strlen($pdf), 'mime_type' => 'application/pdf']);

            // Hanya mahasiswa kedua yang sudah mengumpulkan di tugas terbuka; mahasiswa pertama (akun demo) bisa mencoba.
            if ($student = $students->get(1)) {
                Submission::create([
                    'assignment_id' => $open->id, 'user_id' => $student->id, 'body' => '<p>Jawaban saya: 1) 12, 2) 15, 3) 9.</p>',
                    'status' => SubmissionStatus::Submitted, 'submitted_at' => now()->subHours(3),
                ]);
            }

            $closed = Assignment::create([
                'course_id' => $course->id, 'section_id' => $section->id, 'grade_category_id' => $category?->id,
                'title' => 'Tugas Mandiri: Rangkuman Pertemuan',
                'instructions' => '<p>Tulis rangkuman satu halaman.</p>',
                'due_at' => now()->subDays(2)->setTime(23, 59), 'max_score' => 100, 'allow_late' => false, 'position' => 2,
            ]);

            foreach ($students->values() as $i => $student) {
                $base = ['assignment_id' => $closed->id, 'user_id' => $student->id];
                $on = now()->subDays(3);

                match (true) {
                    $i === 0 => Submission::create($base + [
                        'body' => '<p>Rangkuman saya tentang materi ini.</p>', 'status' => SubmissionStatus::Graded, 'score' => 85,
                        'feedback' => 'Bagus, tambahkan contoh pada paragraf kedua.', 'submitted_at' => $on, 'graded_at' => now()->subDay(), 'graded_by' => $teacher->id,
                    ]),
                    $i === 1 => Submission::create($base + ['body' => '<p>Rangkuman singkat.</p>', 'status' => SubmissionStatus::Submitted, 'submitted_at' => $on]),
                    $i === 2 => Submission::create($base + ['body' => '<p>Maaf terlambat.</p>', 'status' => SubmissionStatus::Late, 'is_late' => true, 'submitted_at' => now()->subDay()]),
                    $i === 3 => Submission::create($base + [
                        'body' => '<p>Rangkuman.</p>', 'status' => SubmissionStatus::Returned,
                        'feedback' => 'Terlalu singkat. Lengkapi dengan minimal tiga poin utama.', 'submitted_at' => $on,
                    ]),
                    $i >= 5 && $i % 2 === 1 => $this->withFile($closed, $student, $on, $teacher, graded: $i % 3 === 0),
                    default => null, // belum mengumpulkan
                };
            }

            AssignmentComment::create(['assignment_id' => $open->id, 'user_id' => $teacher->id, 'body' => 'Kalau ada yang kurang jelas, tanyakan di sini ya.', 'is_private' => false]);

            if ($first = $students->first()) {
                AssignmentComment::create(['assignment_id' => $open->id, 'user_id' => $first->id, 'body' => 'Bu, boleh dikumpulkan dalam bentuk foto?', 'is_private' => true, 'thread_user_id' => $first->id]);
                AssignmentComment::create(['assignment_id' => $open->id, 'user_id' => $teacher->id, 'body' => 'Boleh, asal terbaca jelas.', 'is_private' => true, 'thread_user_id' => $first->id]);
            }
        }
    }

    private function withFile(Assignment $assignment, User $student, \DateTimeInterface $at, User $teacher, bool $graded): void
    {
        $pdf = SamplePdf::make('Tugas '.$student->name);
        $path = "courses/{$assignment->course_id}/assignments/{$assignment->id}/submissions/{$student->id}.pdf";
        Storage::disk(config('courses.disk'))->put($path, $pdf);

        Submission::create([
            'assignment_id' => $assignment->id, 'user_id' => $student->id, 'file_path' => $path, 'file_name' => 'tugas.pdf',
            'file_size' => strlen($pdf), 'mime_type' => 'application/pdf', 'submitted_at' => $at,
            'status' => $graded ? SubmissionStatus::Graded : SubmissionStatus::Submitted,
            'score' => $graded ? 70 + ($student->id % 25) : null, 'graded_at' => $graded ? now()->subDay() : null, 'graded_by' => $graded ? $teacher->id : null,
        ]);
    }
}
