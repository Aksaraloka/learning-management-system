<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Pembaca generik CSV/XLSX: baris pertama jadi kunci (snake_case).
 */
class RowsImport implements ToArray, WithHeadingRow
{
    /**
     * @param  array<int, array<string, mixed>>  $array
     */
    public function array(array $array): void
    {
        // Hanya dipakai lewat Excel::toArray().
    }
}
