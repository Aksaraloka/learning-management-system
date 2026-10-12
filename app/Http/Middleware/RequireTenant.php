<?php

namespace App\Http\Middleware;

use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alias `tenant`: rute hanya ada di subdomain tenant.
 * Alias `central`: rute hanya ada di domain utama (parameter `central`).
 */
class RequireTenant
{
    public function handle(Request $request, Closure $next, string $context = 'tenant'): Response
    {
        $hasTenant = Tenancy::current() !== null;

        abort_if($context === 'tenant' ? ! $hasTenant : $hasTenant, 404);

        return $next($request);
    }
}
