<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membaca subdomain dari host dan memasang tenant aktif ke container.
 * Berjalan di setiap request web (termasuk /livewire/update).
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $subdomain = $this->subdomain($request->getHost());

        if ($subdomain === null) {
            Tenancy::forget();

            return $next($request);
        }

        $tenant = Tenant::query()->where('subdomain', $subdomain)->first();

        abort_if($tenant === null, 404);
        abort_unless($tenant->isActive(), 403, __('ui.tenant.suspended'));

        Tenancy::bind($tenant);

        return $next($request);
    }

    /**
     * Null berarti domain utama (atau host yang tidak dikenal, mis. localhost).
     */
    private function subdomain(string $host): ?string
    {
        $root = (string) config('tenancy.root_domain');
        $host = strtolower($host);

        if ($host === $root || ! str_ends_with($host, '.'.$root)) {
            return null;
        }

        $subdomain = substr($host, 0, -strlen('.'.$root));

        // www.aksaraloka.test dan subdomain tingkat dalam bukan tenant.
        if (in_array($subdomain, config('tenancy.reserved_subdomains'), true) || str_contains($subdomain, '.')) {
            return null;
        }

        return $subdomain;
    }
}
