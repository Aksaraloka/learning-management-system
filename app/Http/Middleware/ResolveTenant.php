<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membaca slug tenant dari parameter rute `{tenant}` (segmen pertama URL) dan memasang
 * tenant aktif ke container. Berjalan di setiap request web, termasuk update Livewire
 * (`/livewire-xxx/update/{tenant?}`). Tanpa parameter = domain utama.
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $slug = $route?->parameter('tenant');

        // Tanpa parameter (mis. /platform/institusi) atau slug `platform` = domain utama.
        if (! is_string($slug) || $slug === '' || $slug === Tenancy::PLATFORM_SLUG) {
            Tenancy::forget();
            $route?->forgetParameter('tenant');

            return $next($request);
        }

        // Jangan diteruskan sebagai argumen ke controller / mount() komponen.
        $route->forgetParameter('tenant');

        $tenant = Tenant::query()->where('slug', strtolower($slug))->first();

        abort_if($tenant === null, 404);
        abort_unless($tenant->isActive(), 403, __('ui.tenant.suspended'));

        Tenancy::bind($tenant);

        return $next($request);
    }
}
