<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileResponse
{
    /**
     * Menyajikan berkas privat dari disk kelas. PDF dan gambar inline (pratinjau) kecuali diminta unduh;
     * tipe lain selalu diunduh. Header mencegah sniffing dan eksekusi konten.
     */
    public static function serve(Request $request, ?string $path, ?string $name, ?string $mime): StreamedResponse
    {
        $disk = Storage::disk(config('courses.disk'));

        abort_unless($path && $disk->exists($path), 404);

        $previewable = $mime === 'application/pdf' || str_starts_with((string) $mime, 'image/');
        $inline = ! $request->boolean('download') && $previewable;

        return $disk->response($path, $name ?: basename($path), [
            'Content-Type' => $mime ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            'Cache-Control' => 'private, max-age=3600',
        ], $inline ? 'inline' : 'attachment');
    }
}
