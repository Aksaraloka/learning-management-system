<?php

namespace App\Http\Controllers;

use App\Enums\MaterialType;
use App\Models\Material;
use App\Support\FileResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MaterialFileController extends Controller
{
    /**
     * Menyajikan berkas materi hanya kepada anggota kelas (atau admin/kepala).
     */
    public function __invoke(Request $request, Material $material): StreamedResponse
    {
        Gate::authorize('view', $material);

        abort_unless($material->type === MaterialType::File && $material->file_path, 404);

        return FileResponse::serve($request, $material->file_path, $material->file_name, $material->mime_type);
    }
}
