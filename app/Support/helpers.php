<?php

use App\Support\Terminology;

if (! function_exists('term')) {
    /**
     * Istilah UI sesuai mode tenant, mis. term('teacher') => "Guru" atau "Dosen".
     */
    function term(string $key, ?string $mode = null): string
    {
        return Terminology::get($key, $mode);
    }
}
