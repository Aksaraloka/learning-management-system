<?php

namespace App\Livewire\Admin\Users;

use App\Actions\Users\UserRowValidator;
use App\Imports\RowsImport;
use App\Jobs\ImportUsersJob;
use App\Models\ImportRun;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Excel as ExcelType;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class Import extends Component
{
    use WithFileUploads;

    private const PREVIEW_ROWS = 200;

    public ?TemporaryUploadedFile $file = null;

    /** @var list<array{line: int, row: array<string, mixed>, errors: list<string>}> */
    public array $preview = [];

    public int $totalRows = 0;

    public int $invalidRows = 0;

    public ?string $readError = null;

    public function mount(): void
    {
        Gate::authorize('import', User::class);
    }

    public function updatedFile(): void
    {
        $this->reset('preview', 'totalRows', 'invalidRows', 'readError');

        $this->validate(['file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120']], [], ['file' => __('ui.users.file')]);

        try {
            $rows = $this->readRows($this->file);
        } catch (Throwable) {
            $this->readError = __('ui.users.unreadable');
            $this->file = null;

            return;
        }

        $validator = new UserRowValidator;
        $this->totalRows = count($rows);

        foreach (array_slice($rows, 0, self::PREVIEW_ROWS) as $i => $row) {
            $result = $validator->validate($row);
            $this->invalidRows += $result['errors'] === [] ? 0 : 1;
            $this->preview[] = ['line' => $i + 2, 'row' => $row, 'errors' => $result['errors']];
        }
    }

    public function import(): void
    {
        Gate::authorize('import', User::class);

        $this->validate(['file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120']]);

        $path = $this->file->store('imports', 'local');

        $run = ImportRun::create([
            'user_id' => auth()->id(),
            'type' => 'users',
            'file_path' => $path,
            'status' => 'queued',
        ]);

        ImportUsersJob::dispatch($run->id);

        $this->reset('file', 'preview', 'totalRows', 'invalidRows');
        $this->dispatch('toast', message: __('ui.users.import_queued'), type: 'success');
    }

    /**
     * @return Collection<int, ImportRun>
     */
    #[Computed]
    public function runs(): Collection
    {
        return ImportRun::query()->with('user')->latest()->limit(10)->get();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readRows(TemporaryUploadedFile $file): array
    {
        $type = match (strtolower($file->getClientOriginalExtension())) {
            'xlsx' => ExcelType::XLSX,
            'xls' => ExcelType::XLS,
            default => ExcelType::CSV,
        };

        /** @var array<int, array<int, array<string, mixed>>> $sheets */
        $sheets = Excel::toArray(new RowsImport, $file->getRealPath(), null, $type);

        return array_values($sheets[0] ?? []);
    }

    public function render(): View
    {
        return view('livewire.admin.users.import')->title(__('ui.users.import'));
    }
}
