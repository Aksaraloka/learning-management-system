<?php

namespace App\Livewire\Concerns;

use App\Support\Upload;

trait ValidatesUploads
{
    /**
     * Aturan unggahan berkas kelas: batas ukuran efektif, tipe dari isi berkas (mimes), dan ekstensi asli
     * harus ada di daftar putih config/courses.php.
     *
     * @return list<string>
     */
    protected function uploadRules(bool $required = false): array
    {
        $allowed = implode(',', config('courses.allowed_mimes'));

        return [$required ? 'required' : 'nullable', 'file', 'max:'.Upload::maxKb(), "mimes:{$allowed}", "extensions:{$allowed}"];
    }
}
