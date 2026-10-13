<?php

namespace App\Actions\Courses;

use App\Models\Course;
use App\Support\StoredFile;
use Illuminate\Http\UploadedFile;

class UpdateCourseBanner
{
    /**
     * Mengganti atau menghapus banner. Gambar baru menggantikan yang lama (berkas lama dihapus dari penyimpanan);
     * tanpa gambar dan tanpa permintaan hapus, banner tidak berubah. Hak akses dan validasi gambar diperiksa pemanggil.
     */
    public function handle(Course $course, ?UploadedFile $image, bool $remove = false): Course
    {
        if ($image) {
            $stored = StoredFile::store($image, "courses/{$course->id}/banner");
            $old = $course->banner_path;

            $course->update(['banner_path' => $stored['file_path'], 'banner_mime' => $stored['mime_type']]);
            StoredFile::delete($old);
        } elseif ($remove && $course->hasBanner()) {
            $old = $course->banner_path;

            $course->update(['banner_path' => null, 'banner_mime' => null]);
            StoredFile::delete($old);
        }

        return $course;
    }
}
