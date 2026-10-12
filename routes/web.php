<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\UserImportTemplateController;
use App\Livewire\Admin\Terms;
use App\Livewire\Admin\Users;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Platform\Tenants;
use App\Livewire\Profile;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Route;

/*
| Tenant ditentukan dari segmen pertama URL (parameter {tenant}) oleh middleware ResolveTenant:
|   /smpn1bandung/login   -> tenant "smpn1bandung"
|   /platform/login       -> domain utama (slug khusus "platform": landing + super admin)
| Satu set rute bersama memakai prefix {tenant}, jadi nama rute (login, home, ...) sama di kedua
| konteks; URL::defaults() mengisi {tenant} otomatis. Alias `tenant` / `central` membatasi rute ke
| satu konteks. Rute dengan segmen tetap didaftarkan sebelum rute bersama.
*/

$tenantSlug = Tenancy::slugPattern();
$anySlug = Tenancy::routeSlugPattern();

// Panel super admin (domain utama saja).
Route::middleware(['auth', 'central', 'tenant.member'])->prefix('platform')->name('platform.')->group(function () {
    Route::get('/institusi', Tenants\Index::class)->name('tenants.index');
});

// Admin institusi (wajib ada tenant di URL).
Route::middleware(['auth', 'tenant', 'tenant.member'])
    ->prefix('{tenant}/admin')->where(['tenant' => $tenantSlug])->name('admin.')->group(function () {
        Route::get('/pengguna', Users\Index::class)->name('users.index');
        Route::get('/pengguna/impor', Users\Import::class)->name('users.import');
        Route::get('/pengguna/impor/templat', UserImportTemplateController::class)->name('users.import.template');
        Route::get('/tahun-ajaran', Terms\Index::class)->name('terms.index');
    });

// Rute bersama: domain utama (/platform/...) atau tenant (/{slug}/...).
Route::prefix('{tenant}')->where(['tenant' => $anySlug])->group(function () {
    Route::view('/kebijakan-privasi', 'privacy')->name('privacy');

    Route::middleware('guest')->group(function () {
        Route::get('/login', Login::class)->name('login');
        Route::get('/lupa-kata-sandi', ForgotPassword::class)->name('password.request');
        Route::get('/reset-kata-sandi/{token}', ResetPassword::class)->name('password.reset');
    });

    Route::middleware(['auth', 'tenant.member'])->group(function () {
        Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
        Route::put('/theme', [SessionController::class, 'theme'])->name('theme.update');
        Route::get('/profil', Profile::class)->name('profile.edit');
    });

    Route::get('/', HomeController::class)->name('home');
});

// "/" membawa pengunjung ke landing platform.
Route::get('/', fn () => redirect()->route('home'))->name('landing');

// Galeri komponen untuk pengembangan; tidak tersedia di production.
if (app()->environment('local')) {
    Route::view('/ui', 'ui')->name('ui');
}
