<?php

namespace App\Support;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Course;

/**
 * Rekap kehadiran per mahasiswa. Hanya sesi yang sudah diisi (punya minimal satu catatan) dihitung;
 * sesi yang baru dibuat dan belum diabsen tidak menurunkan persentase.
 */
class AttendanceSummary
{
    /**
     * @return array<int, array{present: int, excused: int, sick: int, absent: int, total: int, percent: float|null}>
     */
    public static function forCourse(Course $course): array
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
