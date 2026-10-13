<?php

namespace App\Support;

use App\Enums\SubmissionStatus;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Submission;
use Illuminate\Support\Facades\Cache;

/**
 * Ringkasan statistik satu mata kuliah untuk dashboard (dosen, ketua program studi). Dihitung dari buku nilai
 * dan di-cache singkat agar dashboard tetap ringan di mata kuliah besar.
 */
class CourseStats
{
    public const TTL_SECONDS = 300;

    /** Batas bawah nilai huruf: A ≥ 85, B ≥ 70, C ≥ 55, D ≥ 40, selebihnya E. */
    public const BUCKETS = ['A' => 85, 'B' => 70, 'C' => 55, 'D' => 40, 'E' => 0];

    /**
     * @return array{students: int, avg: float|null, buckets: array<string, int>, attendance: float|null, submission_rate: float|null}
     */
    public static function summary(Course $course): array
    {
        return Cache::remember(self::key($course), self::TTL_SECONDS, fn () => self::compute($course));
    }

    public static function forget(Course $course): void
    {
        Cache::forget(self::key($course));
    }

    private static function key(Course $course): string
    {
        return "course-stats:{$course->id}";
    }

    /**
     * @return array{students: int, avg: float|null, buckets: array<string, int>, attendance: float|null, submission_rate: float|null}
     */
    private static function compute(Course $course): array
    {
        $data = Gradebook::compute($course);
        $finals = collect($data['rows'])->pluck('final')->filter(fn ($v) => $v !== null)->values();
        $attendance = collect(AttendanceSummary::forCourse($course))->pluck('percent')->filter(fn ($v) => $v !== null);

        $buckets = array_fill_keys(array_keys(self::BUCKETS), 0);

        foreach ($finals as $final) {
            $buckets[self::letter((float) $final)]++;
        }

        $students = $data['students']->count();
        $assignmentIds = Assignment::query()->where('course_id', $course->id)->where('is_hidden', false)->pluck('id');
        $expected = $students * $assignmentIds->count();
        $submitted = $expected > 0
            ? Submission::query()->whereIn('assignment_id', $assignmentIds)
                ->whereIn('status', [SubmissionStatus::Submitted->value, SubmissionStatus::Late->value, SubmissionStatus::Graded->value])->count()
            : 0;

        return [
            'students' => $students,
            'avg' => $finals->isNotEmpty() ? round((float) $finals->avg(), 1) : null,
            'buckets' => $buckets,
            'attendance' => $attendance->isNotEmpty() ? round((float) $attendance->avg(), 1) : null,
            'submission_rate' => $expected > 0 ? round(min(100, $submitted / $expected * 100), 1) : null,
        ];
    }

    public static function letter(float $score): string
    {
        foreach (self::BUCKETS as $letter => $min) {
            if ($score >= $min) {
                return $letter;
            }
        }

        return 'E';
    }
}
