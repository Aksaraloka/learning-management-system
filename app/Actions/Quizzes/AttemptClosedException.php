<?php

namespace App\Actions\Quizzes;

use RuntimeException;

/**
 * Jawaban ditolak karena percobaan sudah selesai atau melewati batas waktu (menurut jam server).
 */
class AttemptClosedException extends RuntimeException {}
