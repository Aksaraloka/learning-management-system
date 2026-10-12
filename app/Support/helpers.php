<?php

use App\Support\Tenancy;
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

if (! function_exists('tenant_name')) {
    /**
     * Nama institusi aktif, atau nama platform di domain utama.
     */
    function tenant_name(): string
    {
        return Tenancy::current()->name ?? (string) config('app.name');
    }
}
