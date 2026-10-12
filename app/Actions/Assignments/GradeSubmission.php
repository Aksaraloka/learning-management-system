<?php

namespace App\Actions\Assignments;

use App\Enums\SubmissionStatus;
use App\Models\Assignment;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class GradeSubmission
{
    /**
     * Memberi nilai. Tanpa kiriman tetap bisa dinilai (mis. tugas dikumpulkan di kertas); baris kosong dibuat.
     * Ditolak bila tahun ajaran dikunci. Nilai harus 0..max_score.
     *
     * @throws AuthorizationException|ValidationException
     */
    public function handle(Assignment $assignment, User $student, float $score, ?string $feedback, User $grader): Submission
    {
        $this->ensureOpen($assignment);

        if ($score < 0 || $score > $assignment->max_score) {
            throw ValidationException::withMessages([
                'score' => __('assignments.grade.score_range', ['max' => $assignment->max_score]),
            ]);
        }

        return Submission::updateOrCreate(
            ['assignment_id' => $assignment->id, 'user_id' => $student->id],
            [
                'status' => SubmissionStatus::Graded,
                'score' => round($score, 2),
                'feedback' => $feedback !== null && trim($feedback) !== '' ? trim($feedback) : null,
                'graded_at' => now(),
                'graded_by' => $grader->id,
            ],
        );
    }

    /**
     * Mengembalikan kiriman untuk direvisi: nilai dihapus, siswa boleh mengumpulkan ulang.
     *
     * @throws AuthorizationException
     */
    public function returnForRevision(Assignment $assignment, Submission $submission, ?string $feedback): Submission
    {
        $this->ensureOpen($assignment);

        $submission->update([
            'status' => SubmissionStatus::Returned,
            'score' => null,
            'graded_at' => null,
            'feedback' => $feedback !== null && trim($feedback) !== '' ? trim($feedback) : $submission->feedback,
        ]);

        return $submission;
    }

    /**
     * @throws AuthorizationException
     */
    private function ensureOpen(Assignment $assignment): void
    {
        $assignment->loadMissing('course.term');

        if ($assignment->course->term->is_locked) {
            throw new AuthorizationException(__('assignments.grade.locked'));
        }
    }
}
