<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Support\FileResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CourseBannerController extends Controller
{
    /**
     * Banner mata kuliah (privat): hanya anggota mata kuliah, admin, dan ketua program studi.
     */
    public function __invoke(Request $request, Course $course): StreamedResponse
    {
        Gate::authorize('view', $course);

        abort_unless($course->hasBanner(), 404);

        return FileResponse::serve($request, $course->banner_path, 'banner-'.$course->id, $course->banner_mime);
    }
}
