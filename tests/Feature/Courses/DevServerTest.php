<?php

use Illuminate\Foundation\Console\ServeCommand;

it('keeps the temp-dir variables when running artisan serve', function () {
    // Tanpa TMP/TEMP, `artisan serve` di Windows membuat setiap unggahan berkas gagal.
    expect(ServeCommand::$passthroughVariables)->toContain('TMP', 'TEMP', 'APP_ENV');
});
