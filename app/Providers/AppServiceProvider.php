<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Console\ServeCommand;
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

        // `php artisan serve` membuang semua variabel lingkungan di luar daftar ini. Di Windows,
        // hilangnya TMP/TEMP membuat PHP gagal membuat file sementara ("unable to create a temporary
        // file"), sehingga setiap unggahan berkas ditolak.
        if ($this->app->runningInConsole()) {
            ServeCommand::$passthroughVariables = array_values(array_unique([
                ...ServeCommand::$passthroughVariables,
                'TMP', 'TEMP', 'TMPDIR', 'LOCALAPPDATA', 'APPDATA', 'USERPROFILE', 'SYSTEMDRIVE', 'WINDIR', 'COMSPEC', 'PATHEXT',
            ]));
        }
    }
}
