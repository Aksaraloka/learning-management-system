<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Cegah N+1: lazy loading melempar exception saat development.
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
