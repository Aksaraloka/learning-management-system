<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\UserImportTemplateController;
use App\Livewire\Admin\Terms;
use App\Livewire\Admin\Users;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Profile;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::view('/kebijakan-privasi', 'privacy')->name('privacy');

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/lupa-kata-sandi', ForgotPassword::class)->name('password.request');
    Route::get('/reset-kata-sandi/{token}', ResetPassword::class)->name('password.reset');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
    Route::put('/theme', [SessionController::class, 'theme'])->name('theme.update');
    Route::get('/profil', Profile::class)->name('profile.edit');
});

// Admin institusi.
Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/pengguna', Users\Index::class)->name('users.index');
    Route::get('/pengguna/impor', Users\Import::class)->name('users.import');
    Route::get('/pengguna/impor/templat', UserImportTemplateController::class)->name('users.import.template');
    Route::get('/tahun-ajaran', Terms\Index::class)->name('terms.index');
});

// Galeri komponen untuk pengembangan; tidak tersedia di production.
if (app()->environment('local')) {
    Route::view('/ui', 'ui')->name('ui');
}
