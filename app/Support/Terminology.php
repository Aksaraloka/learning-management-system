<?php

namespace App\Support;

class Terminology
{
    public const SCHOOL = 'school';

    public const CAMPUS = 'campus';

    /**
     * Mode tenant aktif. Sebelum ada tenant (landing, panel super admin) memakai mode sekolah.
     */
    public static function mode(): string
    {
        $tenant = app()->bound('tenant') ? app('tenant') : null;

        return ($tenant->level ?? self::SCHOOL) === self::CAMPUS ? self::CAMPUS : self::SCHOOL;
    }

    public static function get(string $key, ?string $mode = null): string
    {
        return __('terms.'.($mode ?? self::mode()).'.'.$key);
    }
}
