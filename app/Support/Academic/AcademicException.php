<?php

namespace App\Support\Academic;

use RuntimeException;

/**
 * Kegagalan memanggil API Academic (jaringan, 5xx, 401/403/404, atau respons tak terduga).
 */
class AcademicException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $status = null)
    {
        parent::__construct($message, $status ?? 0);
    }
}
