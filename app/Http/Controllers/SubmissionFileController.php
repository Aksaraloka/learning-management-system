<?php

namespace App\Http\Controllers;

use App\Models\Submission;
use App\Support\FileResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionFileController extends Controller
{
    /**
     * Berkas kiriman siswa: hanya pemilik, pengelola kelas, dan kepala. Siswa lain tidak pernah.
     */
    public function __invoke(Request $request, Submission $submission): StreamedResponse
    {
        Gate::authorize('view', $submission);

        return FileResponse::serve($request, $submission->file_path, $submission->file_name, $submission->mime_type);
    }
}
