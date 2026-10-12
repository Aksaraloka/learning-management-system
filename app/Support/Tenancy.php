<?php

namespace App\Support;

use App\Models\Tenant;
use Closure;
use Spatie\Permission\PermissionRegistrar;

/**
 * Titik tunggal untuk tenant aktif. Tenant disimpan di container sebagai `tenant`
 * dan ikut menjadi "team" untuk spatie/laravel-permission.
 */
final class Tenancy
{
    public static function current(): ?Tenant
    {
        return app()->bound('tenant') ? app('tenant') : null;
    }

    public static function id(): ?int
    {
        return self::current()?->getKey();
    }

    public static function bind(Tenant $tenant): void
    {
        app()->instance('tenant', $tenant);
        self::registrar()->setPermissionsTeamId($tenant->getKey());
    }

    public static function forget(): void
    {
        app()->forgetInstance('tenant');
        self::registrar()->setPermissionsTeamId(config('tenancy.platform_team_id'));
    }

    /**
     * Jalankan callback dalam konteks tenant tertentu lalu pulihkan konteks sebelumnya.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function run(Tenant $tenant, Closure $callback): mixed
    {
        $previous = self::current();

        self::bind($tenant);

        try {
            return $callback();
        } finally {
            $previous ? self::bind($previous) : self::forget();
        }
    }

    private static function registrar(): PermissionRegistrar
    {
        return app(PermissionRegistrar::class);
    }
}
