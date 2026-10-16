<?php

namespace App\Actions\Auth;

use RuntimeException;

/**
 * Masuk lewat Academic ditolak (status mahasiswa tidak diizinkan, peran tidak dikenal, akun dinonaktifkan).
 * Pesan sudah siap ditampilkan ke pengguna.
 */
class AcademicSignInRejected extends RuntimeException {}
