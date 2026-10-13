<?php

use App\Http\Controllers\AssignmentFileController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\CourseBannerController;
use App\Http\Controllers\GradebookExportController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\MaterialFileController;
use App\Http\Controllers\NotificationOpenController;
use App\Http\Controllers\QuestionImageController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SubmissionFileController;
use App\Http\Controllers\UserImportTemplateController;
use App\Livewire\Admin\Audit;
use App\Livewire\Admin\Terms;
use App\Livewire\Admin\Users;
use App\Livewire\Announcements;
use App\Livewire\Assignments;
use App\Livewire\Attendance;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Courses;
use App\Livewire\Gradebook;
use App\Livewire\Materials;
use App\Livewire\Notifications;
use App\Livewire\Profile;
use App\Livewire\Quizzes;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::view('/kebijakan-privasi', 'privacy')->name('privacy');
Route::get('/manifest.webmanifest', ManifestController::class)->name('manifest');
Route::view('/offline', 'offline')->name('offline');

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
    Route::get('/kelas/{course}/banner', CourseBannerController::class)->name('courses.banner');
    Route::get('/pengguna/{user}/foto', AvatarController::class)->name('users.avatar');

    // Tugas.
    Route::get('/tugas', Assignments\Index::class)->name('assignments.index');
    Route::get('/kelas/{course}/tugas/buat', Assignments\Form::class)->name('assignments.create');
    Route::get('/kelas/{course}/tugas/{assignment}', Assignments\Show::class)->name('assignments.show');
    Route::get('/kelas/{course}/tugas/{assignment}/ubah', Assignments\Form::class)->name('assignments.edit');
    Route::get('/kelas/{course}/tugas/{assignment}/nilai', Assignments\Grade::class)->name('assignments.grade');
    Route::get('/tugas/{assignment}/lampiran/{file}', AssignmentFileController::class)->name('assignments.attachment');
    Route::get('/pengumpulan/{submission}/berkas', SubmissionFileController::class)->name('submissions.file');

    // Kuis.
    Route::get('/kelas/{course}/kuis/buat', Quizzes\Form::class)->name('quizzes.create');
    Route::get('/kelas/{course}/kuis/{quiz}', Quizzes\Show::class)->name('quizzes.show');
    Route::get('/kelas/{course}/kuis/{quiz}/ubah', Quizzes\Form::class)->name('quizzes.edit');
    Route::get('/kelas/{course}/kuis/{quiz}/soal', Quizzes\Questions::class)->name('quizzes.questions');
    Route::get('/kelas/{course}/kuis/{quiz}/soal/buat', Quizzes\QuestionForm::class)->name('quizzes.question.create');
    Route::get('/kelas/{course}/kuis/{quiz}/soal/{question}/ubah', Quizzes\QuestionForm::class)->name('quizzes.question.edit');
    Route::get('/kelas/{course}/kuis/{quiz}/hasil', Quizzes\Results::class)->name('quizzes.results');
    Route::get('/kelas/{course}/kuis/{quiz}/percobaan/{attempt}', Quizzes\Attempt::class)->name('quizzes.attempt');
    Route::get('/kelas/{course}/kuis/{quiz}/percobaan/{attempt}/tinjau', Quizzes\Review::class)->name('quizzes.review');
    Route::get('/soal/{question}/gambar', QuestionImageController::class)->name('questions.image');

    // Buku nilai, presensi, pengumuman, notifikasi.
    Route::get('/kelas/{course}/nilai', Gradebook\Index::class)->name('gradebook.index');
    Route::get('/kelas/{course}/nilai/ekspor', GradebookExportController::class)->name('gradebook.export');
    Route::get('/kelas/{course}/presensi', Attendance\Index::class)->name('attendance.index');
    Route::get('/kelas/{course}/presensi/{session}', Attendance\Session::class)->name('attendance.session');
    Route::get('/pengumuman', Announcements\Index::class)->name('announcements.index');
    Route::get('/notifikasi', Notifications\Index::class)->name('notifications.index');
    Route::get('/notifikasi/{id}/buka', NotificationOpenController::class)->name('notifications.open');
});

// Admin institusi.
Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/pengguna', Users\Index::class)->name('users.index');
    Route::get('/pengguna/impor', Users\Import::class)->name('users.import');
    Route::get('/pengguna/impor/templat', UserImportTemplateController::class)->name('users.import.template');
    Route::get('/tahun-ajaran', Terms\Index::class)->name('terms.index');
    Route::get('/audit', Audit\Index::class)->name('audit.index');
});

// Galeri komponen untuk pengembangan; tidak tersedia di production.
if (app()->environment('local')) {
    Route::view('/ui', 'ui')->name('ui');
}
