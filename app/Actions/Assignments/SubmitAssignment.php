<?php

namespace App\Actions\Assignments;

use App\Enums\SubmissionStatus;
use App\Models\Assignment;
use App\Models\Submission;
use App\Models\User;
use App\Support\StoredFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mews\Purifier\Facades\Purifier;

class SubmitAssignment
{
    /**
     * Siswa mengumpulkan atau mengganti kiriman. Aturan: tenggat (kecuali boleh terlambat), terkunci setelah dinilai,
     * terbuka lagi bila dikembalikan untuk revisi. Minimal teks atau berkas. Berkas lama dihapus saat diganti.
     *
     * @throws ValidationException
     */
    public function handle(Assignment $assignment, User $student, ?string $body, ?UploadedFile $file): Submission
    {
        $existing = Submission::query()->where('assignment_id', $assignment->id)->where('user_id', $student->id)->first();

        if (! $assignment->acceptsSubmissionFrom($existing)) {
            throw ValidationException::withMessages([
                'body' => $existing?->status === SubmissionStatus::Graded ? __('assignments.errors.graded') : __('assignments.errors.closed'),
            ]);
        }

        $body = Purifier::clean((string) $body, 'content');
        $hasBody = trim(strip_tags($body)) !== '';

        if (! $hasBody && ! $file && ! $existing?->file_path) {
            throw ValidationException::withMessages(['body' => __('assignments.errors.empty')]);
        }

        return DB::transaction(function () use ($assignment, $student, $existing, $body, $hasBody, $file) {
            $late = $assignment->isOverdue();

            $attributes = [
                'body' => $hasBody ? $body : null,
                'status' => $late ? SubmissionStatus::Late : SubmissionStatus::Submitted,
                'is_late' => $late,
                'submitted_at' => now(),
                'score' => null,
                'graded_at' => null,
                'graded_by' => null,
            ];

            $oldPath = $existing?->file_path;

            if ($file) {
                $attributes += StoredFile::store($file, "courses/{$assignment->course_id}/assignments/{$assignment->id}/submissions");
            }

            $submission = Submission::updateOrCreate(
                ['assignment_id' => $assignment->id, 'user_id' => $student->id],
                $attributes,
            );

            if ($file && $oldPath && $oldPath !== $submission->file_path) {
                StoredFile::delete($oldPath);
            }

            return $submission;
        });
    }
}
