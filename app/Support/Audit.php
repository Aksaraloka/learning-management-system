<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Mencatat tindakan yang memengaruhi nilai, kehadiran, akses, dan pengaturan akademik.
 * Pelaku diambil dari sesi saat ini (null untuk scheduler/CLI); data sensitif (kata sandi, isi jawaban) tidak pernah dicatat.
 */
class Audit
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function record(string $action, ?Model $subject = null, array $meta = [], ?int $courseId = null, ?int $userId = null): AuditLog
    {
        return AuditLog::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'course_id' => $courseId,
            'meta' => $meta ?: null,
            'ip' => request()->ip(),
        ]);
    }
}
