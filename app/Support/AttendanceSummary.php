<?php

namespace App\Support;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\User;
use App\Support\Academic\AcademicClient;
use App\Support\Academic\AcademicException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Rekap kehadiran per mahasiswa (kunci = id pengguna Learn), satu sumber untuk halaman presensi, buku nilai,
 * ekspor, dashboard, dan statistik.
 *
 * Mata kuliah hasil sinkron Academic (saat integrasi aktif): presensi resmi ada di Academic, jadi rekap diambil dari
 * sana (read-only, cache singkat). Selain itu rekap dihitung dari sesi lokal; hanya sesi yang sudah diisi (punya
 * minimal satu catatan) dihitung, sehingga sesi yang baru dibuat tidak menurunkan persentase.
 */
class AttendanceSummary
{
    /** Setelah Academic gagal dihubungi, jangan coba lagi selama sekian detik agar halaman tidak lambat. */
    private const DOWN_SECONDS = 30;

    /**
     * @return array<int, array{present: int, excused: int, sick: int, absent: int, total: int, percent: float|null, standing?: string|null}>
     */
    public static function forCourse(Course $course): array
    {
        return self::fetch($course)['rows'];
    }

    /**
     * @return array{
     *     official: bool,
     *     available: bool,
     *     rows: array<int, array{present: int, excused: int, sick: int, absent: int, total: int, percent: float|null, standing?: string|null}>
     * }
     */
    public static function fetch(Course $course): array
    {
        if (! $course->isManagedByAcademic()) {
            return ['official' => false, 'available' => true, 'rows' => self::local($course)];
        }

        $rows = self::fromAcademic((int) $course->academic_class_id);

        return ['official' => true, 'available' => $rows !== null, 'rows' => $rows ?? []];
    }

    public static function forget(Course $course): void
    {
        Cache::forget(self::cacheKey((int) $course->academic_class_id));
        Cache::forget(self::downKey((int) $course->academic_class_id));
    }

    /**
     * @return array<int, array{present: int, excused: int, sick: int, absent: int, total: int, percent: float|null, standing?: string|null}>|null null bila Academic tidak dapat dihubungi
     */
    private static function fromAcademic(int $classId): ?array
    {
        if (Cache::has(self::downKey($classId))) {
            return null;
        }

        try {
            $raw = Cache::remember(
                self::cacheKey($classId),
                (int) config('academic.attendance_cache_seconds'),
                fn () => app(AcademicClient::class)->attendance($classId),
            );
        } catch (AcademicException $e) {
            Cache::put(self::downKey($classId), true, self::DOWN_SECONDS);
            Log::warning('Presensi Academic tidak tersedia', ['class_id' => $classId, 'message' => $e->getMessage()]);

            return null;
        }

        $learnIds = User::query()->whereIn('academic_user_id', array_column($raw, 'user_id'))->pluck('id', 'academic_user_id');
        $rows = [];

        foreach ($raw as $row) {
            $learnId = $learnIds[(int) $row['user_id']] ?? null;

            if ($learnId === null) {
                continue;
            }

            $rows[$learnId] = [
                'present' => (int) ($row['present'] ?? 0),
                'excused' => (int) ($row['excused'] ?? 0),
                'sick' => (int) ($row['sick'] ?? 0),
                'absent' => (int) ($row['absent'] ?? 0),
                'total' => (int) ($row['sessions'] ?? 0),
                'percent' => isset($row['percent']) ? round((float) $row['percent'], 1) : null,
                'standing' => isset($row['standing']) ? (string) $row['standing'] : null,
            ];
        }

        return $rows;
    }

    private static function cacheKey(int $classId): string
    {
        return "academic:attendance:{$classId}";
    }

    private static function downKey(int $classId): string
    {
        return "academic:attendance-down:{$classId}";
    }

    /**
     * @return array<int, array{present: int, excused: int, sick: int, absent: int, total: int, percent: float|null}>
     */
    private static function local(Course $course): array
    {
        $taken = AttendanceSession::query()->where('course_id', $course->id)
            ->whereHas('records')->pluck('id');

        $summary = [];

        AttendanceRecord::query()->whereIn('attendance_session_id', $taken)->get(['user_id', 'status'])
            ->groupBy('user_id')
            ->each(function ($records, $userId) use (&$summary) {
                $counts = array_fill_keys(array_column(AttendanceStatus::cases(), 'value'), 0);

                foreach ($records as $record) {
                    $counts[$record->status->value]++;
                }

                $total = array_sum($counts);

                $summary[(int) $userId] = $counts + [
                    'total' => $total,
                    'percent' => $total > 0 ? round($counts[AttendanceStatus::Present->value] / $total * 100, 1) : null,
                ];
            });

        return $summary;
    }
}
