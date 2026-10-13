<?php

namespace App\Support;

use App\Enums\AttemptStatus;
use App\Enums\SubmissionStatus;
use App\Models\Assignment;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Kemajuan belajar mahasiswa per mata kuliah = tugas dan kuis yang sudah dikerjakan ÷ seluruh tugas dan kuis terbit.
 * Tugas dihitung selesai bila sudah dikumpulkan (menunggu nilai, terlambat, atau dinilai); kuis bila ada percobaan
 * yang sudah dikumpulkan. Dihitung dengan beberapa query agregat untuk semua mata kuliah sekaligus.
 */
class CourseProgress
{
    /**
     * @param  iterable<int>  $courseIds
     * @return array<int, array{done: int, total: int, percent: int}>
     */
    public static function forStudent(User $student, iterable $courseIds): array
    {
        $ids = collect($courseIds)->map(fn ($id) => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $assignmentTotals = Assignment::query()->whereIn('course_id', $ids)->where('is_hidden', false)->select('course_id', DB::raw('count(*) as n'))->groupBy('course_id')->pluck('n', 'course_id');
        $quizTotals = Quiz::query()->whereIn('course_id', $ids)->where('is_published', true)->select('course_id', DB::raw('count(*) as n'))->groupBy('course_id')->pluck('n', 'course_id');

        $assignmentDone = Submission::query()
            ->join('assignments', 'assignments.id', '=', 'submissions.assignment_id')
            ->whereNull('assignments.deleted_at')->where('assignments.is_hidden', false)
            ->where('submissions.user_id', $student->id)
            ->whereIn('assignments.course_id', $ids)
            ->whereIn('submissions.status', [SubmissionStatus::Submitted->value, SubmissionStatus::Late->value, SubmissionStatus::Graded->value])
            ->select('assignments.course_id', DB::raw('count(*) as n'))->groupBy('assignments.course_id')->pluck('n', 'course_id');

        $quizDone = QuizAttempt::query()
            ->join('quizzes', 'quizzes.id', '=', 'quiz_attempts.quiz_id')
            ->whereNull('quizzes.deleted_at')->where('quizzes.is_published', true)
            ->where('quiz_attempts.user_id', $student->id)
            ->whereIn('quizzes.course_id', $ids)
            ->where('quiz_attempts.status', '!=', AttemptStatus::InProgress->value)
            ->select('quizzes.course_id', DB::raw('count(distinct quiz_attempts.quiz_id) as n'))->groupBy('quizzes.course_id')->pluck('n', 'course_id');

        $progress = [];

        foreach ($ids as $id) {
            $total = (int) ($assignmentTotals[$id] ?? 0) + (int) ($quizTotals[$id] ?? 0);
            $done = min($total, (int) ($assignmentDone[$id] ?? 0) + (int) ($quizDone[$id] ?? 0));

            $progress[$id] = ['done' => $done, 'total' => $total, 'percent' => $total > 0 ? (int) round($done / $total * 100) : 0];
        }

        return $progress;
    }
}
