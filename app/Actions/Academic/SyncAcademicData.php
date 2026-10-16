<?php

namespace App\Actions\Academic;

use App\Models\AcademicTerm;
use App\Models\Course;
use App\Support\Academic\AcademicClient;
use App\Support\Academic\AcademicException;
use Illuminate\Support\Facades\Log;

/**
 * Sinkron penuh dari Academic: semester, lalu kelas dan peserta tiap semester. Kelas yang hilang dari Academic
 * disembunyikan (soft delete, tidak dihapus permanen). Dipakai `academic:sync` dan sinkron harian sebagai
 * pengaman bila webhook terlewat.
 */
class SyncAcademicData
{
    public function __construct(
        private readonly AcademicClient $client,
        private readonly SyncTerm $syncTerm,
        private readonly SyncClass $syncClass,
    ) {}

    /**
     * @param  int|null  $academicTermId  hanya semester ini (id di Academic); null = semua semester
     * @param  bool  $includeLocked  ikutkan kelas semester terkunci yang sudah pernah disinkron
     * @return array{terms: int, classes: int, hidden: int, failed: int}
     *
     * @throws AcademicException bila Academic tidak dapat dihubungi
     */
    public function handle(?int $academicTermId = null, bool $includeLocked = false): array
    {
        $result = ['terms' => 0, 'classes' => 0, 'hidden' => 0, 'failed' => 0];

        foreach ($this->client->terms() as $termData) {
            if ($academicTermId !== null && (int) $termData['id'] !== $academicTermId) {
                continue;
            }

            $term = $this->syncTerm->handle($termData);
            $result['terms']++;

            if ($academicTermId === null && ! $includeLocked && $term->is_locked && $this->hasSyncedCourses($term)) {
                continue;
            }

            $this->syncClasses($term, $result);
        }

        return $result;
    }

    /**
     * @param  array{terms: int, classes: int, hidden: int, failed: int}  $result
     */
    private function syncClasses(AcademicTerm $term, array &$result): void
    {
        $classes = $this->client->classes($term->academic_term_id);
        $seen = [];

        foreach ($classes as $class) {
            $seen[] = (int) $class['id'];

            try {
                $this->syncClass->handle($class, $term);
                $result['classes']++;
            } catch (AcademicException $e) {
                // Academic tidak terjangkau: hentikan; kegagalan satu kelas saja: catat dan lanjut.
                if ($e->status === null) {
                    throw $e;
                }

                $result['failed']++;
                Log::warning('Sinkron kelas Academic gagal', ['class_id' => $class['id'], 'status' => $e->status, 'message' => $e->getMessage()]);
            }
        }

        $result['hidden'] += Course::query()
            ->where('academic_term_id', $term->id)
            ->whereNotNull('academic_class_id')
            ->whereNotIn('academic_class_id', $seen ?: [0])
            ->get()
            ->each(fn (Course $course) => $course->delete())
            ->count();
    }

    private function hasSyncedCourses(AcademicTerm $term): bool
    {
        return Course::query()->where('academic_term_id', $term->id)->whereNotNull('academic_class_id')->exists();
    }
}
