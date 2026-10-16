<div class="space-y-6">
    @can('create', \App\Models\AcademicTerm::class)
        <x-card :title="$editingId ? __('ui.terms.edit') : __('ui.terms.add')">
            <form wire:submit="save" class="grid gap-4 sm:grid-cols-3">
                <x-input name="name" wire:model="name" :label="__('ui.terms.name')" placeholder="2026/2027 Ganjil" required class="sm:col-span-3" />
                <x-input name="starts_at" type="date" wire:model="starts_at" :label="__('ui.terms.starts_at')" required />
                <x-input name="ends_at" type="date" wire:model="ends_at" :label="__('ui.terms.ends_at')" required />
                <div class="flex items-end gap-2">
                    <x-button type="submit" icon="check">{{ __('ui.actions.save') }}</x-button>
                    @if ($editingId) <x-button variant="secondary" wire:click="cancel">{{ __('ui.actions.cancel') }}</x-button> @endif
                </div>
            </form>
        </x-card>
    @endcan

    <x-table>
        <thead><tr><th>{{ __('ui.terms.name') }}</th><th>{{ __('ui.terms.period') }}</th><th>{{ __('ui.users.status') }}</th><th class="sm:text-right">{{ __('ui.users.actions') }}</th></tr></thead>
        <tbody>
            @forelse ($terms as $term)
                <tr wire:key="term-{{ $term->id }}">
                    <td data-label="{{ __('ui.terms.name') }}" class="font-medium">{{ $term->name }}</td>
                    <td data-label="{{ __('ui.terms.period') }}">{{ $term->starts_at->translatedFormat('j M Y') }} – {{ $term->ends_at->translatedFormat('j M Y') }}</td>
                    <td data-label="{{ __('ui.users.status') }}">
                        <x-badge :variant="$term->is_locked ? 'warning' : 'success'">
                            <x-icon :name="$term->is_locked ? 'lock' : 'check'" class="size-3" /> {{ $term->is_locked ? __('ui.terms.locked') : __('ui.terms.open') }}
                        </x-badge>
                    </td>
                    <td data-label="{{ __('ui.users.actions') }}" class="sm:text-right">
                        <div class="flex flex-wrap justify-end gap-2">
                            @if ($term->isManagedByAcademic())
                                <x-badge variant="neutral">{{ __('ui.terms.managed') }}</x-badge>
                            @endif
                            @can('lock', $term)
                                <x-button variant="secondary" size="sm" wire:click="toggleLock({{ $term->id }})" :icon="$term->is_locked ? 'eye' : 'lock'">
                                    {{ $term->is_locked ? __('ui.terms.unlock') : __('ui.terms.lock') }}
                                </x-button>
                            @endcan
                            @can('update', $term)
                                <x-button variant="ghost" size="sm" icon="pencil" wire:click="edit({{ $term->id }})">{{ __('ui.actions.edit') }}</x-button>
                                <x-button variant="ghost" size="sm" icon="trash-2" wire:click="delete({{ $term->id }})" wire:confirm="{{ __('ui.terms.confirm_delete') }}">{{ __('ui.actions.delete') }}</x-button>
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4"><x-empty-state icon="calendar" /></td></tr>
            @endforelse
        </tbody>
    </x-table>
</div>
