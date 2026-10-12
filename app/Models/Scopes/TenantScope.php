<?php

namespace App\Models\Scopes;

use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * @implements Scope<Model>
 */
class TenantScope implements Scope
{
    /**
     * Saat tenant aktif, semua query otomatis dibatasi ke tenant itu.
     * Tanpa tenant (domain utama) scope tidak membatasi; kode domain utama
     * memang tidak boleh menyentuh data tenant kecuali eksplisit.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if ($tenantId = Tenancy::id()) {
            $builder->where($model->qualifyColumn('tenant_id'), $tenantId);
        }
    }
}
