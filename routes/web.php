<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\MaterialFileController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\UserImportTemplateController;
use App\Livewire\Admin\Terms;
use App\Livewire\Admin\Users;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Courses;
use App\Livewire\Materials;
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

// Kelas, topik/pertemuan, dan materi. Rute dengan segmen tetap (buat) sebelum rute berparameter.
Route::middleware('auth')->group(function () {
    Route::get('/kelas', Courses\Index::class)->name('courses.index');
    Route::get('/kelas/buat', Courses\Form::class)->name('courses.create');
    Route::get('/kelas/{course}', Courses\Show::class)->name('courses.show');
    Route::get('/kelas/{course}/ubah', Courses\Form::class)->name('courses.edit');
    Route::get('/kelas/{course}/anggota', Courses\Members::class)->name('courses.members');
    Route::get('/kelas/{course}/materi/buat', Materials\Form::class)->name('materials.create');
    Route::get('/kelas/{course}/materi/{material}', Materials\Show::class)->name('materials.show');
    Route::get('/kelas/{course}/materi/{material}/ubah', Materials\Form::class)->name('materials.edit');
    Route::get('/materi/{material}/berkas', MaterialFileController::class)->name('materials.file');
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
