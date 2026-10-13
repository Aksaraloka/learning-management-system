<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Konversi input tanggal-waktu form ("Y-m-d H:i", zona waktu aplikasi) dari dan ke Carbon.
 */
class LocalTime
{
    public const FORMAT = 'Y-m-d H:i';

    public static function parse(?string $value): ?Carbon
    {
        return $value !== null && $value !== ''
            ? Carbon::createFromFormat(self::FORMAT, $value, config('app.timezone'))
            : null;
    }

    public static function format(?Carbon $time): string
    {
        return $time?->copy()->timezone(config('app.timezone'))->format(self::FORMAT) ?? '';
    }
}
