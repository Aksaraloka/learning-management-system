<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Penyimpanan berkas privat (disk config 'courses.disk'): nama acak di storage, nama asli di database.
 */
class StoredFile
{
    /**
     * @return array{file_path: string, file_name: string, file_size: int|null, mime_type: string|null}
     */
    public static function store(UploadedFile $file, string $directory): array
    {
        return [
            'file_path' => (string) $file->store($directory, ['disk' => config('courses.disk')]),
            'file_name' => mb_substr(basename((string) $file->getClientOriginalName()), 0, 150),
            'file_size' => $file->getSize() ?: null,
            'mime_type' => $file->getMimeType(),
        ];
    }

    public static function delete(?string $path): void
    {
        if ($path) {
            Storage::disk(config('courses.disk'))->delete($path);
        }
    }

    /**
     * Salin berkas ke nama acak baru (dipakai saat menyalin tugas ke kelas lain). Null bila sumber hilang.
     */
    public static function duplicate(string $path, string $directory): ?string
    {
        $disk = Storage::disk(config('courses.disk'));

        if (! $disk->exists($path)) {
            return null;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $target = trim($directory, '/').'/'.Str::random(40).($extension !== '' ? ".{$extension}" : '');

        return $disk->copy($path, $target) ? $target : null;
    }
}
