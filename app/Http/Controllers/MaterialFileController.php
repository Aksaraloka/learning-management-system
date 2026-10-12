<?php

namespace App\Http\Controllers;

use App\Enums\MaterialType;
use App\Models\Material;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MaterialFileController extends Controller
{
    /**
     * Menyajikan berkas materi hanya kepada anggota kelas (atau admin/kepala).
     * PDF dan gambar ditampilkan inline untuk pratinjau; lainnya diunduh.
     */
    public function __invoke(Request $request, Material $material): StreamedResponse
    {
        Gate::authorize('view', $material);

        abort_unless($material->type === MaterialType::File && $material->file_path, 404);

        $disk = Storage::disk(config('courses.disk'));

        abort_unless($disk->exists($material->file_path), 404);

        $inline = ! $request->boolean('download') && ($material->isPdf() || $material->isImage());

        return $disk->response($material->file_path, $material->file_name, [
            'Content-Type' => $material->mime_type ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            'Cache-Control' => 'private, max-age=3600',
        ], $inline ? 'inline' : 'attachment');
    }
}
