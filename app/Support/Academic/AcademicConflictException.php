<?php

namespace App\Support\Academic;

/**
 * 409 dari Academic, mis. nilai sudah dikunci atau lewat batas. `getMessage()` berisi alasan dari Academic.
 */
class AcademicConflictException extends AcademicException {}
