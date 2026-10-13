<?php

namespace App\Support;

use App\Enums\AttemptStatus;
use App\Enums\SubmissionStatus;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\GradeCategory;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Buku nilai berbobot satu mata kuliah.
 *
 * Aturan:
 *  - Setiap tugas/kuis bernilai 0–100 (nilai ÷ nilai maksimal). Kuis memakai percobaan terbaik yang sudah dinilai.
 *  - Nilai kategori = rata-rata butir yang dihitung; nilai akhir = rata-rata berbobot kategori yang punya nilai
 *    (bobot dinormalkan, jadi kategori kosong tidak menarik nilai ke bawah).
 *  - Butir dihitung bagi mahasiswa bila sudah dinilai, atau sudah ditutup (tenggat/penutupan lewat) dan tidak ada
 *    kiriman yang menunggu penilaian: mahasiswa yang tidak mengerjakan mendapat 0. Kiriman yang menunggu penilaian
 *    dosen tidak dihitung dulu (bukan 0). Butir tanpa kategori tampil tetapi tidak masuk nilai akhir.
 */
class Gradebook
{
    /**
     * @return array{
     *     categories: Collection<int, GradeCategory>,
     *     items: list<array{key: string, type: string, id: int, title: string, category_id: int|null, max: float}>,
     *     students: Collection<int, User>,
     *     rows: array<int, array{items: array<string, array{score: float|null, percent: float|null, pending: bool}>, categories: array<int, float|null>, final: float|null}>,
     *     weightTotal: int
     * }
     */
    public static function compute(Course $course): array
    {
        $categories = $course->gradeCategories()->get();
        $students = $course->students()->reorder('users.name')->get();

        $assignments = Assignment::query()->where('course_id', $course->id)->with('submissions')->orderBy('section_id')->orderBy('position')->get();
        $quizzes = Quiz::query()->where('course_id', $course->id)->where('is_published', true)->orderBy('section_id')->orderBy('position')->get();
        $attempts = QuizAttempt::query()->whereIn('quiz_id', $quizzes->pluck('id'))
            ->where('status', '!=', AttemptStatus::InProgress->value)->get()->groupBy('quiz_id');

        $items = [];

        foreach ($assignments as $a) {
            $items[] = ['key' => 'a'.$a->id, 'type' => 'assignment', 'id' => $a->id, 'title' => $a->title, 'category_id' => $a->grade_category_id, 'max' => (float) $a->max_score];
        }

        foreach ($quizzes as $q) {
            $items[] = ['key' => 'q'.$q->id, 'type' => 'quiz', 'id' => $q->id, 'title' => $q->title, 'category_id' => $q->grade_category_id, 'max' => (float) $q->totalPoints()];
        }

        $rows = [];

        foreach ($students as $student) {
            $cells = [];

            foreach ($assignments as $a) {
                $cells['a'.$a->id] = self::assignmentCell($a, $a->submissions->firstWhere('user_id', $student->id));
            }

            foreach ($quizzes as $q) {
                $cells['q'.$q->id] = self::quizCell($q, ($attempts->get($q->id) ?? collect())->where('user_id', $student->id));
            }

            $perCategory = [];

            foreach ($categories as $category) {
                $percents = [];

                foreach ($items as $item) {
                    $percent = $cells[$item['key']]['percent'];

                    if ($item['category_id'] === $category->id && $percent !== null) {
                        $percents[] = $percent;
                    }
                }

                $perCategory[$category->id] = $percents ? round(array_sum($percents) / count($percents), 2) : null;
            }

            $rows[$student->id] = ['items' => $cells, 'categories' => $perCategory, 'final' => self::finalGrade($categories, $perCategory)];
        }

        return [
            'categories' => $categories,
            'items' => $items,
            'students' => $students,
            'rows' => $rows,
            'weightTotal' => (int) $categories->sum('weight'),
        ];
    }

    /**
     * @param  Collection<int, GradeCategory>  $categories
     * @param  array<int, float|null>  $perCategory
     */
    public static function finalGrade(Collection $categories, array $perCategory): ?float
    {
        $weighted = 0.0;
        $weights = 0;

        foreach ($categories as $category) {
            $percent = $perCategory[$category->id] ?? null;

            if ($percent !== null && $category->weight > 0) {
                $weighted += $percent * $category->weight;
                $weights += $category->weight;
            }
        }

        return $weights > 0 ? round($weighted / $weights, 2) : null;
    }

    /**
     * @return array{score: float|null, percent: float|null, pending: bool}
     */
    private static function assignmentCell(Assignment $assignment, mixed $submission): array
    {
        if ($submission && $submission->status === SubmissionStatus::Graded && $submission->score !== null) {
            return self::cell((float) $submission->score, (float) $assignment->max_score);
        }

        // Kiriman yang menunggu penilaian dosen belum dihitung.
        if ($submission && in_array($submission->status, [SubmissionStatus::Submitted, SubmissionStatus::Late], true)) {
            return ['score' => null, 'percent' => null, 'pending' => true];
        }

        // Tidak mengumpulkan (atau dikembalikan dan tidak diperbaiki) setelah tenggat = 0.
        return $assignment->due_at !== null && $assignment->isOverdue()
            ? ['score' => 0.0, 'percent' => 0.0, 'pending' => false]
            : ['score' => null, 'percent' => null, 'pending' => false];
    }

    /**
     * @param  Collection<int, QuizAttempt>  $attempts  percobaan selesai milik satu mahasiswa
     * @return array{score: float|null, percent: float|null, pending: bool}
     */
    private static function quizCell(Quiz $quiz, Collection $attempts): array
    {
        $graded = $attempts->filter(fn (QuizAttempt $a) => $a->status === AttemptStatus::Graded && $a->score !== null && $a->max_score > 0);

        if ($graded->isNotEmpty()) {
            $best = $graded->sortByDesc(fn (QuizAttempt $a) => $a->score / $a->max_score)->first();

            return self::cell((float) $best->score, (float) $best->max_score);
        }

        if ($attempts->isNotEmpty()) {
            return ['score' => null, 'percent' => null, 'pending' => true];
        }

        return $quiz->hasClosed()
            ? ['score' => 0.0, 'percent' => 0.0, 'pending' => false]
            : ['score' => null, 'percent' => null, 'pending' => false];
    }

    /**
     * @return array{score: float|null, percent: float|null, pending: bool}
     */
    private static function cell(float $score, float $max): array
    {
        return ['score' => $score, 'percent' => $max > 0 ? round(min(100.0, $score / $max * 100), 2) : null, 'pending' => false];
    }
}
