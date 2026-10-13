<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Menyelesaikan percobaan kuis yang waktunya habis tetapi belum ditutup oleh peserta.
Schedule::command('quizzes:finalize-expired')->everyMinute()->withoutOverlapping();
