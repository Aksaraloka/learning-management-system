<?php

namespace App\Actions\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Support\Audit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class SaveAttendance
{
    /**
     * Menyimpan presensi satu sesi. Hanya mahasiswa kelas ini yang diterima (id lain diabaikan); satu catatan per
     * mahasiswa per sesi (upsert). Ditolak bila semester dikunci.
     *
     * @param  array<int|string, array{status: string, note?: string|null}>  $entries  dikunci id mahasiswa
     *
     * @throws AuthorizationException
     */
    public function handle(AttendanceSession $session, array $entries): int
    {
        $session->loadMissing('course.term');

        if ($session->course->isManagedByAcademic()) {
            throw new AuthorizationException(__('attendance.managed'));
        }

        if ($session->course->term->is_locked) {
            throw new AuthorizationException(__('attendance.locked'));
        }

        $studentIds = $session->course->students()->pluck('users.id')->all();
        $saved = 0;

        DB::transaction(function () use ($session, $entries, $studentIds, &$saved) {
            foreach ($entries as $userId => $entry) {
                $status = AttendanceStatus::tryFrom($entry['status']);

                if (! $status || ! in_array((int) $userId, $studentIds, true)) {
                    continue;
                }

                $note = trim((string) ($entry['note'] ?? ''));

                AttendanceRecord::query()->updateOrCreate(
                    ['attendance_session_id' => $session->id, 'user_id' => (int) $userId],
                    ['status' => $status, 'note' => $note !== '' ? mb_substr($note, 0, 255) : null],
                );

                $saved++;
            }
        });

        Audit::record('attendance.saved', $session, ['students' => $saved, 'date' => $session->held_on->toDateString()], $session->course_id);

        return $saved;
    }
}
