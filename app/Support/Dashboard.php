<?php

namespace App\Support;

use App\Enums\AttemptStatus;
use App\Enums\EnrollmentRole;
use App\Enums\Role;
use App\Enums\SubmissionStatus;
use App\Models\AcademicTerm;
use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Data dashboard per peran. Setiap peran hanya menerima data yang boleh dilihatnya: mahasiswa hanya data sendiri,
 * dosen hanya mata kuliah yang diampu, ketua program studi dan admin ringkasan keseluruhan.
 */
class Dashboard
{
    /** Peran tampilan dashboard menurut prioritas: admin > ketua prodi > dosen > mahasiswa. */
    public static function viewFor(User $user): string
    {
        return match (true) {
            $user->hasAppRole(Role::Admin) => 'admin',
            $user->hasAppRole(Role::Principal) => 'principal',
            $user->hasAppRole(Role::Teacher, Role::Homeroom) => 'teacher',
            $user->hasAppRole(Role::Student) => 'student',
            default => 'basic',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function student(User $user): array
    {
        $courses = Course::query()->visibleTo($user)->with('term')->orderBy('name')->get();
        $now = now();

        $rows = $courses->map(function (Course $course) use ($user) {
            $final = Gradebook::compute($course, $user)['rows'][$user->id]['final'] ?? null;

            return [
                'course' => $course,
                'final' => $final,
                'attendance' => AttendanceSummary::forCourse($course)[$user->id]['percent'] ?? null,
            ];
        });

        // Tenggat terdekat: tugas yang belum dikumpulkan dan kuis yang masih ada kesempatannya.
        $submitted = Submission::query()->where('user_id', $user->id)
            ->whereIn('status', [SubmissionStatus::Submitted->value, SubmissionStatus::Late->value, SubmissionStatus::Graded->value])->pluck('assignment_id');

        $deadlines = Assignment::query()->with('course')->whereIn('course_id', $courses->pluck('id'))
            ->whereNotIn('id', $submitted)->where('due_at', '>', $now)->where('due_at', '<=', $now->copy()->addDays(14))->get()
            ->map(fn (Assignment $a) => ['type' => 'assignment', 'title' => $a->title, 'course' => $a->course->name, 'at' => $a->due_at, 'url' => route('assignments.show', ['course' => $a->course_id, 'assignment' => $a->id])]);

        $used = QuizAttempt::query()->where('user_id', $user->id)->select('quiz_id', DB::raw('count(*) as n'))->groupBy('quiz_id')->pluck('n', 'quiz_id');

        $quizzes = Quiz::query()->with('course')->whereIn('course_id', $courses->pluck('id'))->where('is_published', true)
            ->where('closes_at', '>', $now)->where('closes_at', '<=', $now->copy()->addDays(14))->get()
            ->filter(fn (Quiz $q) => ($used[$q->id] ?? 0) < $q->max_attempts)
            ->map(fn (Quiz $q) => ['type' => 'quiz', 'title' => $q->title, 'course' => $q->course->name, 'at' => $q->closes_at, 'url' => route('quizzes.show', ['course' => $q->course_id, 'quiz' => $q->id])]);

        $grades = Submission::query()->with('assignment.course')->where('user_id', $user->id)->where('status', SubmissionStatus::Graded->value)
            ->whereNotNull('score')->latest('graded_at')->limit(5)->get()
            ->map(fn (Submission $s) => ['title' => $s->assignment->title, 'course' => $s->assignment->course->name, 'score' => (float) $s->score, 'max' => (float) $s->assignment->max_score, 'at' => $s->graded_at, 'url' => route('assignments.show', ['course' => $s->assignment->course_id, 'assignment' => $s->assignment_id])])
            ->concat(
                QuizAttempt::query()->with('quiz')->where('user_id', $user->id)->where('status', AttemptStatus::Graded->value)->whereNotNull('score')
                    ->latest('finished_at')->limit(5)->get()
                    ->filter(fn (QuizAttempt $a) => $a->quiz->resultsVisible())
                    ->map(fn (QuizAttempt $a) => ['title' => $a->quiz->title, 'course' => $a->quiz->course->name, 'score' => (float) $a->score, 'max' => (float) $a->max_score, 'at' => $a->finished_at, 'url' => route('quizzes.review', ['course' => $a->quiz->course_id, 'quiz' => $a->quiz_id, 'attempt' => $a->id])])
            )
            ->sortByDesc('at')->take(5)->values();

        return [
            'rows' => $rows,
            'deadlines' => $deadlines->concat($quizzes)->sortBy('at')->take(6)->values(),
            'grades' => $grades,
            'chart' => [
                'labels' => $rows->filter(fn ($r) => $r['final'] !== null)->map(fn ($r) => $r['course']->name)->values()->all(),
                'values' => $rows->pluck('final')->filter(fn ($v) => $v !== null)->values()->all(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function teacher(User $user): array
    {
        $courses = Course::query()->whereHas('enrollments', fn ($q) => $q->where('user_id', $user->id)->where('role', EnrollmentRole::Teacher->value))
            ->with('term')->orderBy('name')->get();
        $ids = $courses->pluck('id');

        $assignments = Assignment::query()->with('course')->whereIn('course_id', $ids)
            ->withCount([
                'submissions as pending_count' => fn ($q) => $q->whereIn('status', SubmissionStatus::awaitingGrading()),
                'submissions as submitted_count' => fn ($q) => $q->whereIn('status', [SubmissionStatus::Submitted->value, SubmissionStatus::Late->value, SubmissionStatus::Graded->value]),
            ])->get();

        $pendingQuizzes = QuizAttempt::query()->where('status', AttemptStatus::Submitted->value)
            ->whereIn('quiz_id', Quiz::query()->whereIn('course_id', $ids)->select('id'))
            ->select('quiz_id', DB::raw('count(*) as n'))->groupBy('quiz_id')->pluck('n', 'quiz_id');

        $needsGrading = $assignments->where('pending_count', '>', 0)->map(fn (Assignment $a) => [
            'type' => 'assignment', 'title' => $a->title, 'course' => $a->course->name, 'count' => $a->pending_count,
            'url' => route('assignments.grade', ['course' => $a->course_id, 'assignment' => $a->id]),
        ])->concat(
            Quiz::query()->with('course')->whereIn('id', $pendingQuizzes->keys())->get()->map(fn (Quiz $q) => [
                'type' => 'quiz', 'title' => $q->title, 'course' => $q->course->name, 'count' => (int) $pendingQuizzes[$q->id],
                'url' => route('quizzes.results', ['course' => $q->course_id, 'quiz' => $q->id, 'tampil' => 'needs']),
            ])
        )->sortByDesc('count')->values();

        $stats = $courses->mapWithKeys(fn (Course $c) => [$c->id => CourseStats::summary($c)]);

        $soon = $assignments->filter(fn (Assignment $a) => $a->due_at !== null && $a->due_at->isFuture() && $a->due_at->lte(now()->addDays(7)))
            ->sortBy('due_at')->take(5)->values();

        return [
            'courses' => $courses,
            'stats' => $stats,
            'needsGrading' => $needsGrading,
            'soon' => $soon,
            'studentCounts' => $stats->map(fn ($s) => $s['students']),
            'chart' => [
                'labels' => $courses->filter(fn ($c) => $stats[$c->id]['avg'] !== null)->map->name->values()->all(),
                'values' => $courses->map(fn ($c) => $stats[$c->id]['avg'])->filter(fn ($v) => $v !== null)->values()->all(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function principal(): array
    {
        $courses = Course::query()->with(['term', 'teachers'])->orderBy('name')->get();
        $stats = $courses->mapWithKeys(fn (Course $c) => [$c->id => CourseStats::summary($c)]);

        $buckets = array_fill_keys(array_keys(CourseStats::BUCKETS), 0);

        foreach ($stats as $s) {
            foreach ($s['buckets'] as $letter => $n) {
                $buckets[$letter] += $n;
            }
        }

        return [
            'courses' => $courses,
            'stats' => $stats,
            'buckets' => $buckets,
            'chart' => [
                'labels' => $courses->filter(fn ($c) => $stats[$c->id]['avg'] !== null)->map->name->values()->all(),
                'values' => $courses->map(fn ($c) => $stats[$c->id]['avg'])->filter(fn ($v) => $v !== null)->values()->all(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function admin(): array
    {
        $byRole = DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_type', User::class)->select('roles.name', DB::raw('count(*) as n'))->groupBy('roles.name')->pluck('n', 'name');

        $labels = [];
        $values = [];

        foreach (Role::cases() as $role) {
            if (($byRole[$role->value] ?? 0) > 0) {
                $labels[] = $role->label();
                $values[] = (int) $byRole[$role->value];
            }
        }

        $terms = AcademicTerm::query()->withCount('courses')->orderByDesc('starts_at')->limit(6)->get()->reverse()->values();

        return [
            'counts' => ['users' => User::count(), 'courses' => Course::count(), 'terms' => AcademicTerm::count(), 'locked' => AcademicTerm::where('is_locked', true)->count()],
            'roles' => ['labels' => $labels, 'values' => $values],
            'terms' => ['labels' => $terms->pluck('name')->all(), 'values' => $terms->pluck('courses_count')->all()],
            'audit' => AuditLog::query()->with('user')->latest('id')->limit(6)->get(),
        ];
    }
}
