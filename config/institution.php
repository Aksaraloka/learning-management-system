<?php

return [

    /*
    | Aplikasi ini melayani satu institusi. Nama dan jenjang diatur lewat .env:
    |   INSTITUTION_NAME="SMP Negeri 1 Bandung"
    |   INSTITUTION_LEVEL=school   # school (SMP/SMA/SMK) atau campus (perguruan tinggi)
    | Jenjang menentukan istilah UI (Guru/Dosen, Siswa/Mahasiswa, Topik/Pertemuan).
    */
    'name' => env('INSTITUTION_NAME', 'Aksaraloka'),

    'level' => env('INSTITUTION_LEVEL', 'school'),
];
