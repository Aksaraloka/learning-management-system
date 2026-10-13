<?php

namespace Database\Seeders;

use App\Actions\Quizzes\FinishAttempt;
use App\Actions\Quizzes\ManageQuestions;
use App\Actions\Quizzes\SaveAttemptAnswer;
use App\Actions\Quizzes\StartAttempt;
use App\Enums\QuestionType;
use App\Models\Course;
use App\Models\Quiz;
use Illuminate\Database\Seeder;

/**
 * Dua kuis per kelas demo: "Kuis Harian" masih dibuka (durasi 20 menit, 2 percobaan; mahasiswa1 belum mencoba) dan
 * "Kuis Evaluasi" yang sudah ditutup dengan beberapa percobaan (satu dinilai otomatis, satu menunggu nilai esai).
 * Jalankan setelah CourseSeeder.
 */
class QuizSeeder extends Seeder
{
    public function run(): void
    {
        $questions = app(ManageQuestions::class);
        $start = app(StartAttempt::class);
        $save = app(SaveAttemptAnswer::class);
        $finish = app(FinishAttempt::class);

        foreach (Course::query()->with(['sections', 'gradeCategories'])->orderBy('id')->get() as $course) {
            $section = $course->sections->first();
            $category = $course->gradeCategories->firstWhere('name', 'Kuis');
            $students = $course->students()->reorder('users.identity_number')->get()->values();

            if (! $section) {
                continue;
            }

            $open = Quiz::create([
                'course_id' => $course->id, 'section_id' => $section->id, 'grade_category_id' => $category?->id,
                'title' => 'Kuis Harian', 'is_published' => true, 'position' => 1,
                'instructions' => '<p>Kerjakan dengan jujur. Jawaban tersimpan otomatis dan waktu berjalan walau halaman ditutup.</p>',
                'opens_at' => now()->subDay(), 'closes_at' => now()->addDays(7), 'duration_minutes' => 20,
                'max_attempts' => 2, 'shuffle_questions' => true, 'shuffle_options' => true,
            ]);

            $this->questions($questions, $open);

            $evaluation = Quiz::create([
                'course_id' => $course->id, 'section_id' => $section->id, 'grade_category_id' => $category?->id,
                'title' => 'Kuis Evaluasi', 'is_published' => true, 'position' => 2,
                'instructions' => '<p>Evaluasi akhir pertemuan.</p>',
                'opens_at' => now()->subDays(5), 'closes_at' => now()->addDay(), 'duration_minutes' => 30, 'max_attempts' => 1,
            ]);

            $this->questions($questions, $evaluation);

            // Mahasiswa kedua menjawab semua dengan benar, ketiga setengah salah; keduanya mengerjakan esai.
            foreach ($students->slice(1, 2)->values() as $i => $student) {
                $attempt = $start->handle($evaluation, $student);

                foreach ($evaluation->questions()->get() as $question) {
                    $key = $question->answer_key ?? [];
                    $value = match ($question->type) {
                        QuestionType::MultipleChoice => $i === 0 ? $key['correct'] : collect($question->options)->firstWhere('id', '!=', $key['correct'])['id'],
                        QuestionType::TrueFalse => ($i === 0) === (bool) $key['correct'] ? 'true' : 'false',
                        QuestionType::ShortAnswer => $i === 0 ? $key['accepted'][0] : 'tidak tahu',
                        QuestionType::Essay => 'Menurut saya konsep ini penting karena menjadi dasar pertemuan berikutnya, dan saya mencontohkannya pada kasus sehari-hari.',
                    };

                    $save->handle($attempt, $question->id, $value);
                }

                $finish->handle($attempt);
            }

            // Kuis evaluasi dianggap sudah ditutup sehingga kunci jawaban terbuka untuk peserta.
            $evaluation->update(['closes_at' => now()->subHours(2)]);
        }
    }

    private function questions(ManageQuestions $questions, Quiz $quiz): void
    {
        $questions->save($quiz, ['type' => QuestionType::MultipleChoice, 'body' => '<p>Hasil dari $2 + 3 \times 4$ adalah ...</p>', 'points' => 2,
            'options' => ['20', '14', '24', '9'], 'correct_index' => 1]);
        $questions->save($quiz, ['type' => QuestionType::TrueFalse, 'body' => '<p>Bilangan prima terkecil adalah 2.</p>', 'points' => 1, 'true_false' => true]);
        $questions->save($quiz, ['type' => QuestionType::ShortAnswer, 'body' => '<p>Ibu kota Indonesia saat ini adalah ...</p>', 'points' => 2,
            'accepted' => ['Jakarta', 'DKI Jakarta']]);
        $questions->save($quiz, ['type' => QuestionType::Essay, 'body' => '<p>Jelaskan dengan kata-katamu sendiri tujuan materi pertemuan ini dan berikan satu contoh penerapannya.</p>', 'points' => 5,
            'rubric' => "Menjelaskan tujuan dengan benar (3 poin)\nMemberi contoh yang relevan (2 poin)"]);
    }
}
