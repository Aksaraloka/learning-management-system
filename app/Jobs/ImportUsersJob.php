<?php

namespace App\Jobs;

use App\Actions\Users\ImportUsers;
use App\Imports\RowsImport;
use App\Models\ImportRun;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ImportUsersJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public function __construct(public int $tenantId, public int $importRunId)
    {
        $this->onQueue('imports');
    }

    public function handle(ImportUsers $import): void
    {
        $tenant = Tenant::findOrFail($this->tenantId);

        Tenancy::run($tenant, function () use ($import) {
            $run = ImportRun::findOrFail($this->importRunId);
            $run->update(['status' => 'processing']);

            /** @var array<int, array<int, array<string, mixed>>> $sheets */
            $sheets = Excel::toArray(new RowsImport, $run->file_path, 'local');

            $import->handle($run, array_values($sheets[0] ?? []));
        });
    }

    public function failed(Throwable $e): void
    {
        $tenant = Tenant::find($this->tenantId);

        if ($tenant) {
            Tenancy::run($tenant, fn () => ImportRun::find($this->importRunId)?->update([
                'status' => 'failed',
                'failed_rows' => [['row' => 0, 'errors' => [$e->getMessage()]]],
            ]));
        }
    }
}
