<?php

$url = rtrim((string) env('ACADEMIC_URL', ''), '/');
$ssoClientId = (string) env('ACADEMIC_SSO_CLIENT_ID', '');
$ssoClientSecret = (string) env('ACADEMIC_SSO_CLIENT_SECRET', '');
$serverClientId = (string) env('ACADEMIC_SERVER_CLIENT_ID', '');
$serverClientSecret = (string) env('ACADEMIC_SERVER_CLIENT_SECRET', '');

return [

    /*
    | Integrasi dengan Aksaraloka Academic (SIAKAD): login SSO, sinkron kelas/peserta, presensi, dan nilai.
    | Aktif hanya bila URL dan semua kredensial terisi; bila tidak, Learn berjalan mandiri seperti biasa.
    */
    'enabled' => $url !== '' && $ssoClientId !== '' && $ssoClientSecret !== '' && $serverClientId !== '' && $serverClientSecret !== '',

    'url' => $url,

    'sso' => [
        'client_id' => $ssoClientId,
        'client_secret' => $ssoClientSecret,
        'scope' => 'profile',
    ],

    'server' => [
        'client_id' => $serverClientId,
        'client_secret' => $serverClientSecret,
        'scope' => 'sync.read grades.write',
    ],

    'webhook_secret' => (string) env('ACADEMIC_WEBHOOK_SECRET', ''),

    /** Toleransi selisih waktu tanda tangan webhook (detik). */
    'webhook_tolerance' => 300,

    'http' => [
        'timeout' => 10,
        'connect_timeout' => 5,
        'retries' => 2,
        'retry_delay_ms' => 200,
        'per_page' => 200,
    ],

    /** Lama rekap presensi dari Academic disimpan di cache (detik), sejalan dengan CourseStats. */
    'attendance_cache_seconds' => 300,
];
