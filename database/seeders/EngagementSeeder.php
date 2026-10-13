<?php

namespace Database\Seeders;

use App\Actions\Announcements\SaveAnnouncement;
use App\Actions\Attendance\SaveAttendance;
use App\Actions\Notifications\Notifier;
use App\Actions\Quizzes\GradeEssays;
use App\Enums\QuestionType;
use App\Enums\Role;
use App\Enums\SubmissionStatus;
use App\Models\Assignment;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

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

            $this->grade($course, $students, $teacher);

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

    /**
     * Nilai yang masuk akal agar dashboard dan buku nilai tidak kosong: tugas yang sudah lewat tenggat dinilai untuk
     * hampir semua mahasiswa (satu dua sengaja tetap menunggu penilaian), dan esai kuis evaluasi dinilai untuk separuhnya.
     * Dibuat langsung lewat model (tanpa notifikasi) karena ini data sejarah.
     *
     * @param  Collection<int, User>  $students
     */
    private function grade(Course $course, Collection $students, User $teacher): void
    {
        foreach (Assignment::query()->where('course_id', $course->id)->where('due_at', '<', now())->get() as $assignment) {
            foreach ($students as $n => $student) {
                $existing = Submission::query()->where('assignment_id', $assignment->id)->where('user_id', $student->id)->first();

                // Satu dari delapan tidak mengumpulkan; yang sudah mengumpulkan dan masih menunggu dibiarkan sebagian.
                if ($n % 8 === 7 || ($existing && $existing->status !== SubmissionStatus::Graded && $n % 5 === 0)) {
                    continue;
                }

                Submission::updateOrCreate(['assignment_id' => $assignment->id, 'user_id' => $student->id], [
                    'body' => $existing->body ?? '<p>Rangkuman dan jawaban saya.</p>',
                    'status' => SubmissionStatus::Graded,
                    'score' => $existing->score ?? 55 + (($n * 11 + $assignment->id * 7) % 41),
                    'submitted_at' => $existing->submitted_at ?? $assignment->due_at->copy()->subHours(5),
                    'graded_at' => now()->subDay(),
                    'graded_by' => $teacher->id,
                ]);
            }
        }

        $evaluation = Quiz::query()->where('course_id', $course->id)->where('title', 'Kuis Evaluasi')->first();

        if (! $evaluation) {
            return;
        }

        $essay = $evaluation->questions()->where('type', QuestionType::Essay->value)->first();

        QuizAttempt::query()->where('quiz_id', $evaluation->id)->orderBy('id')->get()->each(function (QuizAttempt $attempt, int $n) use ($essay) {
            if ($essay && $n % 2 === 0) {
                app(GradeEssays::class)->handle($attempt, [$essay->id => min($essay->points, 2 + ($n % 4))], [$essay->id => 'Penjelasan cukup, tambahkan contoh.']);
            }
        });
    }
}
