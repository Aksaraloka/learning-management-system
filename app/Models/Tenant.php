<?php

namespace App\Models;

use App\Support\Terminology;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'subdomain', 'level', 'plan', 'status', 'settings'])]
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
     * URL absolut ke tenant ini, mengikuti skema & port dari APP_URL.
     */
    public function url(string $path = '/'): string
    {
        $app = parse_url((string) config('app.url')) + ['scheme' => 'http'];
        $port = isset($app['port']) ? ':'.$app['port'] : '';

        return $app['scheme'].'://'.$this->subdomain.'.'.config('tenancy.root_domain').$port.'/'.ltrim($path, '/');
    }
}
