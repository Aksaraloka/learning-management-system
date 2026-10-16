<?php

namespace App\Jobs;

use App\Actions\Academic\SyncAcademicData;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncAcademicJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 900;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public ?int $academicTermId = null, public bool $includeLocked = false) {}

    public function handle(SyncAcademicData $sync): void
    {
        if (! config('academic.enabled')) {
            return;
        }

        $sync->handle($this->academicTermId, $this->includeLocked);
    }
}
