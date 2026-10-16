<?php

namespace App\Console\Commands;

use App\Actions\Academic\SyncAcademicData;
use App\Jobs\SyncAcademicJob;
use App\Support\Academic\AcademicException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('academic:sync {--term= : Hanya semester ini (id di Academic)} {--all : Ikutkan kelas semester terkunci yang sudah pernah disinkron} {--queue : Kirim ke antrean, jangan dijalankan langsung}')]
#[Description('Sinkron semester, kelas, dan peserta dari Aksaraloka Academic')]
class SyncAcademicCommand extends Command
{
    public function handle(SyncAcademicData $sync): int
    {
        if (! config('academic.enabled')) {
            $this->components->warn('Integrasi Academic nonaktif (isi ACADEMIC_URL dan kredensial di .env).');

            return self::SUCCESS;
        }

        $termId = $this->option('term') !== null ? (int) $this->option('term') : null;
        $includeLocked = (bool) $this->option('all');

        if ($this->option('queue')) {
            SyncAcademicJob::dispatch($termId, $includeLocked);
            $this->components->info('Sinkron dikirim ke antrean.');

            return self::SUCCESS;
        }

        try {
            $result = $sync->handle($termId, $includeLocked);
        } catch (AcademicException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("{$result['terms']} semester, {$result['classes']} kelas disinkron; {$result['hidden']} disembunyikan; {$result['failed']} gagal.");

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
