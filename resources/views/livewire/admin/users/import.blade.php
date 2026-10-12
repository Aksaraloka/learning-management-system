<div class="space-y-6" @if ($this->runs->contains(fn ($r) => in_array($r->status, ['queued', 'processing']))) wire:poll.3s @endif>
    <x-card :title="__('ui.users.import')">
        <p class="text-sm text-text-muted">{{ __('ui.users.import_help') }}</p>
        <p class="mt-2 text-sm"><a href="{{ route('admin.users.import.template') }}" class="inline-flex items-center gap-1 text-primary underline"><x-icon name="download" class="size-4" /> {{ __('ui.users.template') }}</a></p>

        <div class="mt-4 space-y-2">
            <label for="import-file" class="block text-sm font-medium">{{ __('ui.users.file') }}</label>
            <input id="import-file" type="file" wire:model="file" accept=".csv,.xlsx,.xls" class="input">
            @error('file') <p class="text-sm text-danger" role="alert">{{ $message }}</p> @enderror
            @if ($readError) <p class="text-sm text-danger" role="alert">{{ $readError }}</p> @endif
            <div wire:loading wire:target="file" class="text-sm text-text-muted">{{ __('ui.loading') }}</div>
        </div>
    </x-card>

    @if ($preview)
        <x-card :title="__('ui.users.preview')" :padding="false">
            <div class="flex flex-wrap items-center gap-2 border-b border-border px-4 py-3 text-sm">
                <x-badge>{{ __('ui.users.rows_total', ['n' => $totalRows]) }}</x-badge>
                <x-badge variant="danger">{{ __('ui.users.rows_invalid', ['n' => $invalidRows]) }}</x-badge>
                @if ($totalRows > count($preview)) <span class="text-text-muted">{{ __('ui.users.preview_limited', ['n' => count($preview)]) }}</span> @endif
            </div>

            <x-table class="max-h-96 overflow-y-auto">
                <thead><tr><th>#</th><th>{{ __('ui.profile.name') }}</th><th>{{ term('identity') }}</th><th>{{ __('ui.profile.roles') }}</th><th>{{ __('ui.users.status') }}</th></tr></thead>
                <tbody>
                    @foreach ($preview as $p)
                        <tr wire:key="row-{{ $p['line'] }}">
                            <td data-label="#">{{ $p['line'] }}</td>
                            <td data-label="{{ __('ui.profile.name') }}">{{ $p['row']['name'] ?? '' }}</td>
                            <td data-label="{{ term('identity') }}">{{ $p['row']['identity_number'] ?? '' }}</td>
                            <td data-label="{{ __('ui.profile.roles') }}">{{ $p['row']['role'] ?? '' }}</td>
                            <td data-label="{{ __('ui.users.status') }}">
                                @if ($p['errors'])
                                    <span class="text-sm text-danger">{{ implode(' ', $p['errors']) }}</span>
                                @else
                                    <x-badge variant="success">OK</x-badge>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>

            <div class="flex justify-end border-t border-border p-4">
                <x-button wire:click="import" wire:loading.attr="disabled" wire:target="import" icon="upload">{{ __('ui.users.start_import') }}</x-button>
            </div>
        </x-card>
    @endif

    <x-card :title="__('ui.users.history')">
        @forelse ($this->runs as $run)
            <div class="border-b border-border py-3 last:border-0" wire:key="run-{{ $run->id }}">
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <span class="font-medium">{{ $run->created_at->translatedFormat('j M Y H:i') }}</span>
                    <x-badge :variant="match($run->status) { 'done' => 'success', 'failed' => 'danger', default => 'warning' }">{{ __('ui.users.run_'.$run->status) }}</x-badge>
                    @if ($run->status === 'done')
                        <span class="text-text-muted">{{ __('ui.users.run_result', ['ok' => $run->success_rows, 'total' => $run->total_rows]) }}</span>
                    @endif
                </div>

                @if ($run->failed_rows)
                    <details class="mt-2 text-sm">
                        <summary class="cursor-pointer text-danger">{{ __('ui.users.failed_rows', ['n' => count($run->failed_rows)]) }}</summary>
                        <ul class="mt-2 space-y-1">
                            @foreach ($run->failed_rows as $f)
                                <li><span class="font-medium">{{ __('ui.users.row') }} {{ $f['row'] }}:</span> {{ implode(' ', $f['errors']) }}</li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </div>
        @empty
            <x-empty-state icon="file-spreadsheet" />
        @endforelse
    </x-card>
</div>
