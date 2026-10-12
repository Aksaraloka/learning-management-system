<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// Galeri komponen untuk pengembangan; tidak tersedia di production.
if (app()->environment('local')) {
    Route::view('/ui', 'ui')->name('ui');
}
