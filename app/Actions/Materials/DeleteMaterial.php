<?php

namespace App\Actions\Materials;

use App\Models\Material;
use Illuminate\Support\Facades\Storage;

class DeleteMaterial
{
    /**
     * Menghapus materi beserta berkas di storage.
     */
    public function handle(Material $material): void
    {
        if ($material->file_path) {
            Storage::disk(config('courses.disk'))->delete($material->file_path);
        }

        $material->delete();
    }
}
