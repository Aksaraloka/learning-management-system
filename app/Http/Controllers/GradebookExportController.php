<?php

namespace App\Http\Controllers;

use App\Exports\GradebookExport;
use App\Models\Course;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GradebookExportController extends Controller
{
    /**
     * Ekspor Excel buku nilai: pengelola mata kuliah dan ketua program studi (daftar peserta lengkap).
     */
    public function __invoke(Course $course): BinaryFileResponse
    {
        Gate::authorize('viewRoster', $course);

        return Excel::download(new GradebookExport($course), 'nilai-'.Str::slug($course->name).'.xlsx');
    }
}
