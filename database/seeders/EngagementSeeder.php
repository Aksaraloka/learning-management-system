<?php

namespace Database\Seeders;

use App\Actions\Announcements\SaveAnnouncement;
use App\Actions\Attendance\SaveAttendance;
use App\Actions\Notifications\Notifier;
use App\Enums\Role;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Data demo Fase 5: presensi (3 sesi per kelas dengan campuran status), pengumuman kelas dan universitas yang
 * disematkan, serta notifikasi (pengumuman, kuis yang masih dibuka). Jalankan setelah QuizSeeder.
 */
class EngagementSeeder extends Seeder
{
    public function run(): void
    {
        $save = app(SaveAttendance::class);
        $announce = app(SaveAnnouncement::class);
        $admin = User::role(Role::Admin->value)->first();

        foreach (Course::query()->with('sections')->orderBy('id')->get() as $course) {
            $teacher = $course->teachers()->firstOrFail();
            $students = $course->students()->reorder('users.identity_number')->get()->values();

            foreach ([21, 14, 7] as $i => $daysAgo) {
                $session = AttendanceSession::create([
                    'course_id' => $course->id,
                    'section_id' => $course->sections->get($i)?->id,
                    'held_on' => now()->subDays($daysAgo)->toDateString(),
                    'title' => 'Pertemuan '.($i + 1),
                ]);

                $entries = [];

                foreach ($students as $n => $student) {
                    // Sebagian besar hadir; sisanya bergantian izin/sakit/alpa agar rekap bervariasi.
                    $status = ($n + $i) % 7 === 0 ? 'absent' : (($n + $i) % 5 === 0 ? 'sick' : (($n + $i) % 6 === 0 ? 'excused' : 'present'));
                    $entries[$student->id] = ['status' => $status, 'note' => $status === 'sick' ? 'Surat dokter menyusul' : null];
                }

                $save->handle($session, $entries);
            }

            $announce->handle($teacher, [
                'course_id' => $course->id,
                'title' => 'Selamat datang di '.$course->name,
                'body' => '<p>Silakan baca <strong>kontrak perkuliahan</strong> di pertemuan pertama dan kumpulkan tugas tepat waktu.</p>',
                'is_pinned' => true,
            ]);

            $announce->handle($teacher, [
                'course_id' => $course->id,
                'title' => 'Jadwal kuis pekan ini',
                'body' => '<p>Kuis dibuka sampai akhir pekan. Pastikan koneksi stabil; jawaban tersimpan otomatis.</p>',
                'is_pinned' => false,
            ]);
        }

        if ($admin) {
            $announce->handle($admin, [
                'course_id' => null,
                'title' => 'Pengumuman akademik semester ini',
                'body' => '<p>Batas pengisian KRS dan jadwal ujian tengah semester dapat dilihat di portal akademik.</p>',
                'is_pinned' => true,
            ]);
        }

        // Kuis yang masih terbuka diberitahukan ke pesertanya (seeder membuatnya tanpa melalui form).
        Quiz::query()->where('is_published', true)->whereNull('notified_at')->each(fn (Quiz $quiz) => app(Notifier::class)->quizPublished($quiz));
    }
}
