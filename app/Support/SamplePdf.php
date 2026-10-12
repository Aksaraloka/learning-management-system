<?php

namespace App\Support;

class SamplePdf
{
    /**
     * PDF satu halaman yang valid (dengan tabel xref) berisi satu baris teks. Untuk data demo dan test.
     */
    public static function make(string $text): string
    {
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], preg_replace('/[^\x20-\x7E]/', '', $text) ?? '');
        $stream = "BT /F1 20 Tf 40 150 Td ({$text}) Tj ET";

        $objects = [
            '<</Type/Catalog/Pages 2 0 R>>',
            '<</Type/Pages/Kids[3 0 R]/Count 1>>',
            '<</Type/Page/Parent 2 0 R/MediaBox[0 0 420 300]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>',
            '<</Length '.strlen($stream)."\n>>\nstream\n{$stream}\nendstream",
            '<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer<</Root 1 0 R/Size '.(count($objects) + 1).">>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
