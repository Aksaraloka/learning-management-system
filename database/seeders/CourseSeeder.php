<?php

namespace Database\Seeders;

use App\Enums\EnrollmentRole;
use App\Enums\MaterialType;
use App\Enums\Role;
use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\GradeCategory;
use App\Models\Material;
use App\Models\Section;
use App\Models\User;
use App\Support\SamplePdf;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * 4 mata kuliah demo lengkap dengan pertemuan, materi teks/berkas/tautan/video, dan peserta.
 * Dijalankan setelah DemoSeeder.
 */
class CourseSeeder extends Seeder
{
    public function run(): void
    {
        $term = AcademicTerm::query()->orderByDesc('starts_at')->firstOrFail();
        $students = User::query()->role(Role::Student->value)->orderBy('identity_number')->get();

        foreach ($this->definitions() as $i => $def) {
            $course = Course::create([
                'academic_term_id' => $term->id,
                'name' => $def['name'],
                'code' => Course::generateCode(),
                'credits' => $def['credits'],
                'description' => $def['description'],
            ]);

            foreach (GradeCategory::DEFAULTS as $n => [$categoryName, $weight]) {
                $course->gradeCategories()->create(['name' => $categoryName, 'weight' => $weight, 'position' => $n + 1]);
            }

            $teacher = User::where('email', "dosen{$def['teacher']}@aksaraloka.test")->firstOrFail();
            Enrollment::create(['course_id' => $course->id, 'user_id' => $teacher->id, 'role' => EnrollmentRole::Teacher]);

            // Mata kuliah 1-2: mahasiswa 1-15. Mata kuliah 3-4: mahasiswa 1-5 (akun demo ber-email) dan 16-30.
            $members = $i < 2 ? $students->slice(0, 15) : $students->slice(0, 5)->merge($students->slice(15));
            Enrollment::insert($members->map(fn (User $s) => [
                'course_id' => $course->id, 'user_id' => $s->id, 'role' => EnrollmentRole::Student->value,
                'created_at' => now(), 'updated_at' => now(),
            ])->all());

            foreach ($def['sections'] as $position => $title) {
                $section = Section::create([
                    'course_id' => $course->id,
                    'title' => 'Pertemuan '.($position + 1).': '.$title,
                    'position' => $position + 1,
                ]);

                $this->materials($course, $section, $title, $position === 0);
            }
        }
    }

    private function materials(Course $course, Section $section, string $topic, bool $withVideo): void
    {
        $items = [
            ['type' => MaterialType::Text, 'title' => "Ringkasan: {$topic}",
                'body' => "<h2>{$topic}</h2><p>Bacalah ringkasan berikut sebelum mengerjakan latihan.</p><ul><li>Konsep utama</li><li>Contoh soal</li></ul><p>Contoh rumus: \$a^2 + b^2 = c^2\$</p>"],
            ['type' => MaterialType::File, 'title' => "Slide {$topic} (PDF)"],
            ['type' => MaterialType::Link, 'title' => 'Bacaan tambahan', 'url' => 'https://id.wikipedia.org/wiki/'.rawurlencode(str_replace(' ', '_', $topic))],
        ];

        if ($withVideo) {
            $items[] = ['type' => MaterialType::Video, 'title' => 'Video pengantar', 'url' => 'https://www.youtube.com/watch?v=aqz-KE-bpKQ'];
        }

        foreach ($items as $position => $item) {
            $attributes = [
                'section_id' => $section->id, 'type' => $item['type'], 'title' => $item['title'],
                'body' => $item['body'] ?? null, 'url' => $item['url'] ?? null, 'position' => $position + 1,
            ];

            if ($item['type'] === MaterialType::File) {
                $pdf = SamplePdf::make($item['title']);
                $path = "courses/{$course->id}/demo-{$section->id}.pdf";
                Storage::disk(config('courses.disk'))->put($path, $pdf);

                $attributes += [
                    'file_path' => $path, 'file_name' => str($topic)->slug().'.pdf',
                    'file_size' => strlen($pdf), 'mime_type' => 'application/pdf',
                ];
            }

            Material::create($attributes);
        }
    }

    /**
     * @return list<array{name: string, teacher: int, credits: int, description: string, sections: list<string>}>
     */
    private function definitions(): array
    {
        return [
            ['name' => 'Algoritma dan Pemrograman', 'teacher' => 1, 'credits' => 3, 'description' => 'Dasar berpikir algoritmik dan pemrograman terstruktur.',
                'sections' => ['Pengantar Algoritma', 'Percabangan', 'Perulangan', 'Fungsi']],
            ['name' => 'Basis Data', 'teacher' => 2, 'credits' => 3, 'description' => 'Perancangan dan penggunaan basis data relasional.',
                'sections' => ['Model Relasional', 'SQL Dasar', 'Normalisasi']],
            ['name' => 'Jaringan Komputer', 'teacher' => 3, 'credits' => 3, 'description' => 'Konsep jaringan dan protokol internet.',
                'sections' => ['Model OSI', 'Pengalamatan IP', 'Routing']],
            ['name' => 'Pemrograman Web', 'teacher' => 1, 'credits' => 4, 'description' => 'Membangun aplikasi web modern.',
                'sections' => ['HTML dan CSS', 'JavaScript', 'Backend dengan PHP']],
        ];
    }
}
