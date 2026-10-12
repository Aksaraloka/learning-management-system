<?php

return [

    // Disk penyimpanan berkas materi (private; diakses lewat controller yang memeriksa hak akses).
    // Ganti ke "s3" untuk S3/Cloudflare R2.
    'disk' => env('COURSE_DISK', 'local'),

    // Batas unggah per berkas (KB). Batas efektif juga dibatasi php.ini (upload_max_filesize, post_max_size).
    'max_upload_kb' => 51200,

    // Tipe berkas yang boleh diunggah (divalidasi dari isi berkas, bukan hanya ekstensi).
    'allowed_mimes' => [
        'pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'csv', 'txt',
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'zip', 'mp3', 'mp4',
    ],

    // Jumlah pertemuan maksimum per mata kuliah di mode kampus.
    'max_meetings' => 16,
];
