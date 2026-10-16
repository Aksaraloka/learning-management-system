<?php

namespace App\Http\Controllers;

use App\Actions\Academic\SyncClass;
use App\Actions\Academic\SyncTerm;
use App\Models\Course;
use App\Support\Academic\AcademicClient;
use App\Support\Academic\AcademicException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Tautan dari Academic: `/kelas/academic/{id}` mengarah ke mata kuliah yang cocok. Kelas yang belum ada di Learn
 * disinkron dulu. Bukan anggota = 403; tidak ada di Academic = 404.
 */
class AcademicClassLinkController extends Controller
{
    public function __invoke(int $classId, AcademicClient $client, SyncTerm $syncTerm, SyncClass $syncClass): RedirectResponse
    {
        abort_unless(config('academic.enabled'), 404);

        $course = Course::query()->where('academic_class_id', $classId)->first();

        if (! $course) {
            try {
                $found = $client->findClass($classId);
            } catch (AcademicException $e) {
                abort(503, $e->getMessage());
            }

            abort_if($found === null, 404);

            $course = $syncClass->handle($found['class'], $syncTerm->handle($found['term']));
        }

        Gate::authorize('view', $course);

        return redirect()->route('courses.show', $course);
    }
}
