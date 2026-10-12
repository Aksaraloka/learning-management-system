<?php

if (! function_exists('term')) {
    /**
     * Istilah UI perguruan tinggi, mis. term('teacher') => "Dosen" (lang/id/terms.php).
     */
    function term(string $key): string
    {
        return __('terms.'.$key);
    }
}

if (! function_exists('institution_name')) {
    /**
     * Nama universitas (config 'institution.name').
     */
    function institution_name(): string
    {
        return (string) config('institution.name');
    }
}
