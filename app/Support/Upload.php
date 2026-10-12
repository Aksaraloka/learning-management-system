<?php

namespace App\Support;

class Upload
{
    /**
     * Batas unggah efektif (KB): config courses.max_upload_kb, dibatasi php.ini
     * (upload_max_filesize dan post_max_size) supaya UI tidak menjanjikan lebih dari yang bisa diterima server.
     */
    public static function maxKb(): int
    {
        $limits = [(int) config('courses.max_upload_kb')];

        foreach (['upload_max_filesize', 'post_max_size'] as $key) {
            $bytes = ini_parse_quantity((string) ini_get($key));

            if ($bytes > 0) {
                $limits[] = intdiv($bytes, 1024);
            }
        }

        return min($limits);
    }

    /**
     * Ukuran dalam format FilePond ("50MB" / "500KB").
     */
    public static function filepondSize(): string
    {
        $kb = self::maxKb();

        return $kb >= 1024 ? intdiv($kb, 1024).'MB' : $kb.'KB';
    }

    public static function label(): string
    {
        return str_replace(['MB', 'KB'], [' MB', ' KB'], self::filepondSize());
    }

    public static function humanSize(?int $bytes): string
    {
        if ($bytes === null) {
            return '';
        }

        return match (true) {
            $bytes >= 1048576 => number_format($bytes / 1048576, 1, ',', '.').' MB',
            $bytes >= 1024 => number_format($bytes / 1024, 0, ',', '.').' KB',
            default => $bytes.' B',
        };
    }
}
