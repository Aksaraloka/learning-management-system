<?php

namespace App\Actions\Academic;

use App\Models\Course;
use App\Models\User;
use App\Support\Academic\AcademicClient;
use App\Support\Academic\AcademicStatus;
use App\Support\Audit;
use App\Support\CourseStats;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Menerapkan satu event webhook Academic. Event tidak membawa data lengkap (hanya id), jadi data diambil ulang
 * lewat API dan diterapkan dengan action sinkron yang sama dengan `academic:sync`. Tipe yang tidak dikenal
 * diabaikan agar Academic boleh menambah event baru tanpa memecah Learn.
 */
class HandleAcademicEvent
{
    public function __construct(
        private readonly AcademicClient $client,
        private readonly SyncTerm $syncTerm,
        private readonly SyncClass $syncClass,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(string $type, array $data): void
    {
        match ($type) {
            'roster.updated', 'class.updated' => $this->refreshClass((int) ($data['class_id'] ?? 0)),
            'class.deleted' => $this->hideClass((int) ($data['class_id'] ?? 0)),
            'term.updated' => $this->refreshTerm((int) ($data['term_id'] ?? 0)),
            'user.updated' => $this->refreshUser((int) ($data['user_id'] ?? 0)),
            'student.status_changed' => $this->applyStatus((int) ($data['user_id'] ?? 0), (string) ($data['status'] ?? '')),
            'grades.locked' => $this->lockGrades((int) ($data['class_id'] ?? 0)),
            default => null,
        };
    }

    private function refreshClass(int $classId): void
    {
        if ($classId === 0) {
            return;
        }

        $course = Course::withTrashed()->with('term')->where('academic_class_id', $classId)->first();
        $found = $this->client->findClass($classId, $course?->term->academic_term_id);

        if ($found === null) {
            $course?->delete();

            return;
        }

        $this->syncClass->handle($found['class'], $this->syncTerm->handle($found['term']));
    }

    /** Disembunyikan (soft delete); kiriman, kuis, dan nilai tetap tersimpan. */
    private function hideClass(int $classId): void
    {
        if ($classId !== 0) {
            Course::query()->where('academic_class_id', $classId)->get()->each(fn (Course $course) => $course->delete());
        }
    }

    private function refreshTerm(int $termId): void
    {
        foreach ($this->client->terms() as $term) {
            if ((int) $term['id'] === $termId) {
                $this->syncTerm->handle($term);

                return;
            }
        }
    }

    /**
     * Data pengguna (nama, email, NIM) ikut dimutakhirkan lewat sinkron ulang kelas-kelas yang ia ikuti.
     */
    private function refreshUser(int $academicUserId): void
    {
        $user = User::query()->where('academic_user_id', $academicUserId)->first();

        if (! $user) {
            return;
        }

        Course::query()->whereNotNull('academic_class_id')
            ->whereHas('enrollments', fn ($q) => $q->where('user_id', $user->id))
            ->pluck('academic_class_id')
            ->each(fn (int $classId) => $this->refreshClass($classId));
    }

    /**
     * Status yang tidak diizinkan masuk mencabut semua sesi pengguna itu (dan token ingat-saya). Datanya tetap ada.
     */
    private function applyStatus(int $academicUserId, string $status): void
    {
        $user = User::query()->where('academic_user_id', $academicUserId)->first();

        if (! $user || AcademicStatus::allowsAccess($status)) {
            return;
        }

        DB::table((string) config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        $user->forceFill(['remember_token' => Str::random(60)])->save();

        Audit::record('auth.sessions_revoked', $user, ['status' => $status]);
    }

    private function lockGrades(int $classId): void
    {
        Course::query()->where('academic_class_id', $classId)->get()->each(function (Course $course) {
            $course->update(['grades_locked' => true]);
            CourseStats::forget($course);
        });
    }
}
