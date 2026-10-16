<?php

namespace App\Actions\Gradebook;

use App\Enums\AcademicGradeComponent;
use App\Models\Course;
use App\Models\User;
use App\Support\Academic\AcademicClient;
use App\Support\Academic\AcademicConflictException;
use App\Support\Academic\AcademicException;
use App\Support\Academic\AcademicValidationException;
use App\Support\Audit;
use App\Support\Gradebook;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Kirim satu komponen nilai (tugas/UTS/UAS) sebuah mata kuliah ke Academic. Skor per mahasiswa dihitung hanya oleh
 * `Gradebook::componentScores`. Hanya dosen pengampu atau admin (Policy `pushGrades`); mahasiswa tanpa skor tidak
 * dikirim, dan yang belum punya nilai lengkap dilaporkan sebagai `waiting`.
 */
class PushGradesToAcademic
{
    public function __construct(private readonly AcademicClient $client) {}

    /**
     * @return array{sent: int, updated: int, skipped: list<array{user_id: int|string, name: string|null, reason: string}>, waiting: int}
     *
     * @throws AuthorizationException
     * @throws AcademicConflictException 409: nilai terkunci atau lewat batas (pesan dari Academic)
     * @throws AcademicValidationException 422
     * @throws AcademicException
     */
    public function handle(Course $course, AcademicGradeComponent $component, User $actor): array
    {
        Gate::forUser($actor)->authorize('pushGrades', $course);

        if ($actor->academic_user_id === null) {
            throw new AcademicException(__('gradebook.academic.not_linked'));
        }

        $data = Gradebook::compute($course);
        $scores = Gradebook::componentScores($course, $component, $data);
        $students = $data['students']->keyBy('id');

        $payload = [];

        foreach ($scores as $studentId => $entry) {
            $student = $students->get($studentId);

            if ($entry['score'] !== null && $student?->academic_user_id !== null) {
                $payload[] = ['user_id' => $student->academic_user_id, 'score' => round($entry['score'], 2)];
            }
        }

        $waiting = count(array_filter($scores, fn (array $entry) => $entry['waiting']));

        if ($payload === []) {
            return ['sent' => 0, 'updated' => 0, 'skipped' => [], 'waiting' => $waiting];
        }

        $result = $this->client->pushGrades($course->academic_class_id, $component->value, $actor->academic_user_id, $payload);

        $names = User::query()->whereIn('academic_user_id', array_column($result['skipped'], 'user_id'))->pluck('name', 'academic_user_id');

        $skipped = array_map(fn (array $row) => [
            'user_id' => $row['user_id'] ?? '',
            'name' => $names[$row['user_id'] ?? 0] ?? null,
            'reason' => (string) ($row['reason'] ?? ''),
        ], $result['skipped']);

        Audit::record('gradebook.pushed', $course, [
            'component' => $component->value,
            'updated' => $result['updated'],
            'skipped' => count($skipped),
            'waiting' => $waiting,
        ], $course->id, $actor->id);

        return ['sent' => count($payload), 'updated' => $result['updated'], 'skipped' => $skipped, 'waiting' => $waiting];
    }
}
