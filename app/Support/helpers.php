<?php

use App\Support\Terminology;

if (! function_exists('term')) {
    /**
     * Istilah UI sesuai jenjang institusi, mis. term('teacher') => "Guru" atau "Dosen".
     */
    function term(string $key, ?string $mode = null): string
    {
        return Terminology::get($key, $mode);
    }
}

if (! function_exists('institution_name')) {
    /**
     * Nama institusi (config 'institution.name').
     */
    function institution_name(): string
    {
        return (string) config('institution.name');
    }
}
