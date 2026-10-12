<?php

use App\Http\Middleware\EnsureTenantMember;
use App\Http\Middleware\RequireTenant;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Tenant harus sudah terpasang sebelum session/auth memuat pengguna.
        $middleware->web(prepend: [ResolveTenant::class]);

        $middleware->alias([
            'tenant' => RequireTenant::class.':tenant',
            'central' => RequireTenant::class.':central',
            'tenant.member' => EnsureTenantMember::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
