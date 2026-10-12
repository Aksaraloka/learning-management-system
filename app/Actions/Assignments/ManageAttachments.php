<?php

namespace App\Actions\Assignments;

use App\Models\Assignment;
use App\Models\AssignmentFile;
use App\Support\StoredFile;
use Illuminate\Http\UploadedFile;

class ManageAttachments
{
    public const MAX_PER_ASSIGNMENT = 10;

    public function add(Assignment $assignment, UploadedFile $file): AssignmentFile
    {
        return $assignment->attachments()->create(
            StoredFile::store($file, "courses/{$assignment->course_id}/assignments/{$assignment->id}")
        );
    }

    public function remove(AssignmentFile $file): void
    {
        StoredFile::delete($file->file_path);

        $file->delete();
    }

    /**
     * Hapus permanen semua berkas tugas (lampiran dosen dan kiriman mahasiswa) dari storage.
     */
    public function purge(Assignment $assignment): void
    {
        foreach ($assignment->attachments()->pluck('file_path') as $path) {
            StoredFile::delete($path);
        }

        foreach ($assignment->submissions()->whereNotNull('file_path')->pluck('file_path') as $path) {
            StoredFile::delete($path);
        }
    }
}
