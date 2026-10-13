<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Jejak audit yang hanya bisa ditambah: tidak ada ubah/hapus lewat aplikasi.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $action
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property int|null $course_id
 * @property array<string, mixed>|null $meta
 * @property string|null $ip
 * @property Carbon $created_at
 * @property-read User|null $user
 */
#[Fillable(['user_id', 'action', 'subject_type', 'subject_id', 'course_id', 'meta', 'ip'])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit log tidak boleh diubah.'));
        static::deleting(fn () => throw new LogicException('Audit log tidak boleh dihapus.'));
    }

    /** Label tindakan; titik pada kode tindakan menjadi garis bawah karena kunci terjemahan Laravel memakai titik sebagai pemisah. */
    public static function labelFor(string $action): string
    {
        $key = 'audit.actions.'.str_replace('.', '_', $action);
        $label = __($key);

        return $label === $key ? $action : $label;
    }

    public function label(): string
    {
        return self::labelFor($this->action);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
