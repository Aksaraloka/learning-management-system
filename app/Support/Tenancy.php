<?php

namespace App\Support;

use App\Models\Tenant;
use Closure;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;

/**
 * Titik tunggal untuk tenant aktif. Tenant disimpan di container sebagai `tenant`,
 * ikut menjadi "team" untuk spatie/laravel-permission, dan menjadi nilai default
 * parameter rute `{tenant}` sehingga route('login') otomatis menjadi /{slug}/login.
 */
final class Tenancy
{
    /**
     * Segmen URL untuk domain utama (landing + super admin): /platform/login, /platform/profil, ...
     * Slug ini dicadangkan sehingga tidak bisa dipakai tenant.
     */
    public const PLATFORM_SLUG = 'platform';

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
        URL::defaults(['tenant' => $tenant->slug]);
    }

    public static function forget(): void
    {
        app()->forgetInstance('tenant');
        self::registrar()->setPermissionsTeamId(config('tenancy.platform_team_id'));
        URL::defaults(['tenant' => self::PLATFORM_SLUG]);
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

    /**
     * Pola regex slug tenant: huruf kecil/angka/strip (3-32), bukan slug yang dicadangkan.
     */
    public static function slugPattern(): string
    {
        $reserved = implode('|', array_map('preg_quote', config('tenancy.reserved_slugs')));

        return '(?!(?:'.$reserved.')$)(?!livewire-)[a-z0-9][a-z0-9-]{1,30}[a-z0-9]';
    }

    /**
     * Pola segmen `{tenant}` pada rute bersama: slug tenant atau `platform`.
     */
    public static function routeSlugPattern(): string
    {
        return self::PLATFORM_SLUG.'|'.self::slugPattern();
    }

    private static function registrar(): PermissionRegistrar
    {
        return app(PermissionRegistrar::class);
    }
}
