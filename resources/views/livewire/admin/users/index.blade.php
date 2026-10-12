<div class="space-y-4">
    @if ($revealed)
        <div class="flex items-start justify-between gap-3 rounded-card border border-warning bg-warning/10 p-4" role="alert">
            <div class="text-sm">
                <p class="font-semibold">{{ __('ui.users.password_reset_for', ['name' => $revealed['name']]) }}</p>
                <p class="mt-1">{{ __('ui.users.new_password') }}: <code class="rounded bg-surface px-2 py-0.5 font-mono text-base select-all">{{ $revealed['password'] }}</code></p>
                <p class="mt-1 text-text-muted">{{ __('ui.users.password_once') }}</p>
            </div>
            <x-button variant="ghost" size="sm" wire:click="dismiss">{{ __('ui.actions.close') }}</x-button>
        </div>
    @endif

    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div class="grid flex-1 gap-3 sm:max-w-xl sm:grid-cols-2">
            <x-input name="search" type="search" wire:model.live.debounce.400ms="search" :label="__('ui.actions.search')" :placeholder="__('ui.users.search_placeholder')" />
            <x-select name="role" wire:model.live="role" :label="__('ui.profile.roles')" :placeholder="__('ui.users.all_roles')" :options="$roleOptions" />
        </div>
        @can('import', \App\Models\User::class)
            <x-button :href="route('admin.users.import')" icon="file-spreadsheet" wire:navigate>{{ __('ui.users.import') }}</x-button>
        @endcan
    </div>

    <x-table>
        <thead><tr><th>{{ __('ui.profile.name') }}</th><th>{{ term('identity') }}</th><th>Email</th><th>{{ __('ui.profile.roles') }}</th><th class="sm:text-right">{{ __('ui.users.actions') }}</th></tr></thead>
        <tbody>
            @forelse ($users as $u)
                <tr wire:key="user-{{ $u->id }}">
                    <td data-label="{{ __('ui.profile.name') }}" class="font-medium">{{ $u->name }}</td>
                    <td data-label="{{ term('identity') }}">{{ $u->identity_number ?? '—' }}</td>
                    <td data-label="Email">{{ $u->email ?? '—' }}</td>
                    <td data-label="{{ __('ui.profile.roles') }}"><div class="flex flex-wrap justify-end gap-1 sm:justify-start">
                        @foreach ($u->roles as $r)
                            <x-badge variant="{{ $r->name === 'admin' ? 'warning' : 'neutral' }}">{{ \App\Enums\Role::tryFrom($r->name)?->label() ?? $r->name }}</x-badge>
                        @endforeach
                    </div></td>
                    <td data-label="{{ __('ui.users.actions') }}" class="sm:text-right">
                        @can('resetPassword', $u)
                            <x-button variant="secondary" size="sm" icon="key-round"
                                      wire:click="resetPassword({{ $u->id }})"
                                      wire:confirm="{{ __('ui.users.confirm_reset', ['name' => $u->name]) }}">{{ __('ui.users.reset_password') }}</x-button>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><x-empty-state icon="users" /></td></tr>
            @endforelse
        </tbody>
    </x-table>

    <x-pagination :paginator="$users" />
</div>
