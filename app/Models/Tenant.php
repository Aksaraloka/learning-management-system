<?php

namespace App\Models;

use App\Support\Terminology;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug', 'level', 'plan', 'status', 'settings'])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isCampus(): bool
    {
        return $this->level === Terminology::CAMPUS;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * URL absolut ke tenant ini: {APP_URL}/{slug}/{path}.
     */
    public function url(string $path = '/'): string
    {
        $path = trim($path, '/');

        return rtrim((string) config('app.url'), '/').'/'.$this->slug.($path === '' ? '' : '/'.$path);
    }
}
