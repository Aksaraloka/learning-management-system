<?php

namespace App\Support\Academic;

/**
 * 422 dari Academic: kiriman ditolak karena tidak valid.
 */
class AcademicValidationException extends AcademicException
{
    /**
     * @param  array<string, mixed>  $errors
     */
    public function __construct(string $message, public readonly array $errors = [])
    {
        parent::__construct($message, 422);
    }
}
