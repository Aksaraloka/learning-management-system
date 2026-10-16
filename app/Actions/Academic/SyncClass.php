<?php

namespace App\Actions\Academic;

use App\Actions\Setup\EnsureRoles;
use App\Enums\EnrollmentRole;
use App\Enums\Role;
use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\User;
use App\Support\Academic\AcademicClient;
use App\Support\CourseStats;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Upsert satu kelas Academic menjadi mata kuliah Learn beserta dosen dan mahasiswanya.
 *
 * Idempoten: dicocokkan lewat `courses.academic_class_id`, sinkron ulang tidak menggandakan apa pun. Academic
 * pemilik peserta, jadi pendaftaran yang tidak ada di daftar Academic dicabut; kiriman tugas dan percobaan kuis
 * mahasiswa itu tetap tersimpan. Deskripsi, banner, pertemuan, materi, tugas, dan kuis tidak disentuh.
 */
class SyncClass
{
    public function __construct(
        private readonly AcademicClient $client,
        private readonly UpsertAcademicUser $upsertUser,
    ) {}

    /**
     * @param  array<string, mixed>  $class  satu baris `/terms/{term}/classes`
     * @param  list<array<string, mixed>>|null  $roster  isi `/classes/{class}/roster`; null = ambil dari Academic
     */
    public function handle(array $class, AcademicTerm $term, ?array $roster = null): Course
    {
        $roster ??= $this->client->roster($class['id']);

        $course = DB::transaction(function () use ($class, $term, $roster): Course {
            $course = $this->upsertCourse($class, $term);

            $desired = [];

            foreach ((array) ($class['teachers'] ?? []) as $teacher) {
                $user = $this->upsertUser->handle($teacher);
                $this->ensureBaseRole($user, Role::Teacher);
                $desired[$user->id] = EnrollmentRole::Teacher;
            }

            foreach ($roster as $student) {
                $user = $this->upsertUser->handle($student);
                $this->ensureBaseRole($user, Role::Student);
                $desired[$user->id] ??= EnrollmentRole::Student;
            }

            $this->syncEnrollments($course, $desired);

            return $course;
        });

        CourseStats::forget($course);

        return $course;
    }

    /**
     * @param  array<string, mixed>  $class
     */
    private function upsertCourse(array $class, AcademicTerm $term): Course
    {
        $course = Course::withTrashed()->where('academic_class_id', (int) $class['id'])->first() ?? new Course;

        $credits = (int) ($class['subject']['credits'] ?? 0);

        $course->fill([
            'academic_term_id' => $term->id,
            'name' => $this->courseName($class),
            'credits' => $credits >= 1 && $credits <= 24 ? $credits : null,
            'grades_locked' => (bool) ($class['grades_locked'] ?? false),
            'academic_class_id' => (int) $class['id'],
        ]);

        $isNew = ! $course->exists;

        if ($isNew) {
            $course->code = $this->joinCode($class);
        }

        $course->save();

        if ($course->trashed()) {
            $course->restore();
        }

        if ($isNew) {
            $course->createDefaultGradeCategories();
        }

        return $course;
    }

    /**
     * Nama mata kuliah + label kelas/seksi, mis. "Basis Data A".
     *
     * @param  array<string, mixed>  $class
     */
    private function courseName(array $class): string
    {
        $subject = trim((string) ($class['subject']['name'] ?? ''));
        $label = trim((string) ($class['class_label'] ?? $class['section'] ?? ''));
        $name = trim($subject.' '.$label);

        return Str::limit($name !== '' ? $name : 'Kelas '.$class['id'], 255, '');
    }

    /**
     * Kode mata kuliah dari Academic bila muat dan belum dipakai kelas lain; bila bentrok, kode acak.
     *
     * @param  array<string, mixed>  $class
     */
    private function joinCode(array $class): string
    {
        $candidate = Course::normalizeCode((string) ($class['subject']['code'] ?? ''));

        if ($candidate !== '' && strlen($candidate) <= 8 && ! Course::withTrashed()->where('code', $candidate)->exists()) {
            return $candidate;
        }

        return Course::generateCode();
    }

    /**
     * Pengguna baru dari Academic tanpa peran apa pun diberi peran dasar; peran yang sudah ada tidak diubah
     * (peran disinkronkan saat login).
     */
    private function ensureBaseRole(User $user, Role $role): void
    {
        if (! $user->roles()->exists()) {
            app(EnsureRoles::class)->handle();
            $user->assignRole($role->value);
        }
    }

    /**
     * @param  array<int, EnrollmentRole>  $desired  user_id => peran kelas
     */
    private function syncEnrollments(Course $course, array $desired): void
    {
        $existing = $course->enrollments()->get()->keyBy('user_id');

        foreach ($desired as $userId => $role) {
            $enrollment = $existing->get($userId);

            if (! $enrollment) {
                $course->enrollments()->create(['user_id' => $userId, 'role' => $role]);
            } elseif ($enrollment->role !== $role) {
                $enrollment->update(['role' => $role]);
            }
        }

        $course->enrollments()->whereNotIn('user_id', array_keys($desired) ?: [0])->delete();
    }
}
