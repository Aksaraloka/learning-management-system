<?php

namespace App\Actions\Quizzes;

use App\Models\Course;
use App\Models\Quiz;
use Carbon\CarbonInterface;
use Mews\Purifier\Facades\Purifier;

class SaveQuiz
{
    /**
     * Membuat atau memperbarui kuis. Instruksi disanitasi (Purifier). Pertemuan dan kategori divalidasi pemanggil.
     *
     * @param  array{section_id: int, grade_category_id: int|null, title: string, instructions?: string|null, is_published: bool, opens_at: CarbonInterface|null, closes_at: CarbonInterface|null, duration_minutes: int|null, shuffle_questions: bool, shuffle_options: bool, max_attempts: int, show_result_at: CarbonInterface|null}  $data
     */
    public function handle(Course $course, array $data, ?Quiz $quiz = null): Quiz
    {
        $attributes = [
            'course_id' => $course->id,
            'section_id' => $data['section_id'],
            'grade_category_id' => $data['grade_category_id'],
            'title' => trim($data['title']),
            'instructions' => Purifier::clean((string) ($data['instructions'] ?? ''), 'content') ?: null,
            'is_published' => $data['is_published'],
            'opens_at' => $data['opens_at'],
            'closes_at' => $data['closes_at'],
            'duration_minutes' => $data['duration_minutes'],
            'shuffle_questions' => $data['shuffle_questions'],
            'shuffle_options' => $data['shuffle_options'],
            'max_attempts' => $data['max_attempts'],
            'show_result_at' => $data['show_result_at'],
        ];

        if ($quiz) {
            $quiz->update($attributes);

            return $quiz;
        }

        return Quiz::create($attributes + [
            'position' => (int) Quiz::query()->where('section_id', $data['section_id'])->max('position') + 1,
        ]);
    }
}
