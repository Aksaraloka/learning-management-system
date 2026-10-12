<?php

namespace App\Support;

class Terminology
{
    public const SCHOOL = 'school';

    public const CAMPUS = 'campus';

    /**
     * Jenjang institusi dari config('institution.level'): sekolah (default) atau kampus.
     */
    public static function mode(): string
    {
        return config('institution.level') === self::CAMPUS ? self::CAMPUS : self::SCHOOL;
    }

    public static function get(string $key, ?string $mode = null): string
    {
        return __('terms.'.($mode ?? self::mode()).'.'.$key);
    }
}
