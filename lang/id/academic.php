<?php

return [
    'sso' => [
        'denied' => 'Masuk dengan akun kampus dibatalkan atau ditolak.',
        'invalid_state' => 'Sesi masuk tidak valid atau sudah kedaluwarsa. Silakan coba lagi.',
        'token_failed' => 'Gagal menyelesaikan masuk dengan akun kampus. Silakan coba lagi.',
        'forbidden' => 'Akun kampus Anda tidak diizinkan masuk ke LMS.',
        'profile_failed' => 'Profil akun kampus tidak dapat dibaca. Silakan coba lagi.',
        'unknown_role' => 'Peran akun kampus Anda belum dikenali oleh LMS. Hubungi admin akademik.',
        'status_rejected' => 'Status kemahasiswaan Anda (:status) tidak diizinkan masuk ke LMS. Hubungi bagian akademik.',
        'account_disabled' => 'Akun Anda dinonaktifkan di LMS. Hubungi administrator.',
    ],

    'errors' => [
        'unreachable' => 'Academic tidak dapat dihubungi. Coba lagi beberapa saat lagi.',
        'unexpected_response' => 'Academic mengirim respons yang tidak dikenali.',
        'token_failed' => 'Gagal mendapatkan akses ke Academic. Periksa kredensial integrasi.',
        'conflict' => 'Academic menolak perubahan ini.',
        'invalid' => 'Data yang dikirim ditolak oleh Academic.',
        'failed' => 'Academic menjawab dengan kesalahan (:status).',
    ],
];
