<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentFile;
use App\Support\FileResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssignmentFileController extends Controller
{
    /**
     * Lampiran tugas dari guru: anggota kelas, admin, dan kepala.
     */
    public function __invoke(Request $request, Assignment $assignment, AssignmentFile $file): StreamedResponse
    {
        // Lampiran harus milik tugas di URL, bila tidak id lampiran kelas lain bisa ditebak.
        abort_unless($file->assignment_id === $assignment->id, 404);

        Gate::authorize('view', $assignment);

        return FileResponse::serve($request, $file->file_path, $file->file_name, $file->mime_type);
    }
}
