<?php

namespace App\Exports;

use App\Models\Course;
use App\Support\AttendanceSummary;
use App\Support\Gradebook;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

/**
 * Ekspor buku nilai: satu baris per mahasiswa, kolom per tugas/kuis (nilai mentah), nilai kategori (0–100),
 * nilai akhir, dan persentase kehadiran. Sel teks yang diawali = + - @ diberi apostrof agar tidak dibaca sebagai rumus.
 */
class GradebookExport implements FromArray, ShouldAutoSize
{
    public function __construct(private Course $course) {}

    /**
     * @return list<list<float|string|null>>
     */
    public function array(): array
    {
        $data = Gradebook::compute($this->course);
        $attendance = AttendanceSummary::forCourse($this->course);

        $header = [__('gradebook.nim'), __('gradebook.student')];

        foreach ($data['items'] as $item) {
            $header[] = self::safe($item['title'].' (/'.rtrim(rtrim(number_format($item['max'], 2, '.', ''), '0'), '.').')');
        }

        foreach ($data['categories'] as $category) {
            $header[] = self::safe($category->name.' (%'.$category->weight.')');
        }

        $header[] = __('gradebook.final');
        $header[] = __('gradebook.attendance').' (%)';

        $rows = [$header];

        foreach ($data['students'] as $student) {
            $row = $data['rows'][$student->id];
            $line = [self::safe((string) $student->identity_number), self::safe($student->name)];

            foreach ($data['items'] as $item) {
                $cell = $row['items'][$item['key']];
                $line[] = $cell['pending'] ? __('gradebook.pending_short') : $cell['score'];
            }

            foreach ($data['categories'] as $category) {
                $line[] = $row['categories'][$category->id];
            }

            $line[] = $row['final'];
            $line[] = $attendance[$student->id]['percent'] ?? null;

            $rows[] = $line;
        }

        return $rows;
    }

    public static function safe(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
