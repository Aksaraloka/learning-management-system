<?php

namespace App\Providers;

use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Endpoint update Livewire membawa slug tenant (/livewire-xxx/update/{tenant?}) agar
        // request update tahu tenant-nya. Didaftarkan di register() supaya mendahului rute
        // bawaan Livewire, yang otomatis tidak dibuat bila rute kustom sudah ada.
        Livewire::setUpdateRoute(fn ($handle, $path) => Route::post($path.'/{tenant?}', $handle)
            ->where('tenant', Tenancy::routeSlugPattern())
            ->name('tenant.livewire.update'));
    }

    public function boot(): void
    {
        // Cegah N+1: lazy loading melempar exception saat development.
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
