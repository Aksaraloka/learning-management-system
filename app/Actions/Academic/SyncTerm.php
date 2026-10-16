<?php

namespace App\Actions\Academic;

use App\Models\AcademicTerm;
use Illuminate\Support\Str;

/**
 * Upsert satu semester dari Academic (dicocokkan lewat `academic_term_id`). Semester yang pernah
 * disembunyikan dipulihkan bila muncul lagi.
 */
class SyncTerm
{
    /**
     * @param  array<string, mixed>  $data  satu baris `/terms`
     */
    public function handle(array $data): AcademicTerm
    {
        $academicId = (int) $data['id'];

        $term = AcademicTerm::withTrashed()->where('academic_term_id', $academicId)->first() ?? new AcademicTerm;

        $term->fill([
            'name' => $this->displayName($data),
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'is_locked' => (bool) ($data['is_locked'] ?? false),
            'academic_term_id' => $academicId,
        ]);
        $term->save();

        if ($term->trashed()) {
            $term->restore();
        }

        return $term;
    }

    /**
     * Nama tampilan "2026/2027 Ganjil": nama semester Academic diberi akhiran semester bila belum memuatnya.
     *
     * @param  array<string, mixed>  $data
     */
    private function displayName(array $data): string
    {
        $name = trim((string) ($data['name'] ?? ''));
        $semester = trim((string) ($data['semester'] ?? ''));

        if ($semester !== '' && ! Str::contains(Str::lower($name), Str::lower($semester))) {
            $name = trim($name.' '.Str::ucfirst($semester));
        }

        return Str::limit($name !== '' ? $name : 'Semester '.$data['id'], 100, '');
    }
}
