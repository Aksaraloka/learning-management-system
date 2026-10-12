<div class="space-y-6">
    @if ($created)
        <div class="rounded-card border border-success bg-success/10 p-4 text-sm" role="status">
            <p class="font-semibold">{{ __('ui.tenants.created', ['name' => $created['tenant']]) }}</p>
            <p class="mt-1">URL: <a href="{{ $created['url'] }}" class="text-primary underline">{{ $created['url'] }}</a></p>
            <p>Email admin: <code class="select-all">{{ $created['email'] }}</code> · {{ __('ui.users.new_password') }}: <code class="select-all font-mono">{{ $created['password'] }}</code></p>
            <p class="mt-1 text-text-muted">{{ __('ui.users.password_once') }}</p>
        </div>
    @endif

    <x-card :title="__('ui.tenants.add')">
        <form wire:submit="create" class="grid gap-4 sm:grid-cols-2">
            <x-input name="name" wire:model="name" :label="__('ui.tenants.name')" required />
            <x-input name="slug" wire:model="slug" :label="__('ui.tenants.slug')" :hint="rtrim(config('app.url'), '/').'/'.($slug ?: 'slug-anda')" autocapitalize="none" required />
            <x-select name="level" wire:model="level" :label="__('ui.tenants.level')" :options="['school' => __('ui.tenants.school'), 'campus' => __('ui.tenants.campus')]" />
            <x-input name="plan" wire:model="plan" :label="__('ui.tenants.plan')" />
            <x-input name="admin_name" wire:model="admin_name" :label="__('ui.tenants.admin_name')" required />
            <x-input name="admin_email" type="email" wire:model="admin_email" :label="__('ui.tenants.admin_email')" required />
            <div class="sm:col-span-2"><x-button type="submit" icon="plus">{{ __('ui.actions.add') }}</x-button></div>
        </form>
    </x-card>

    <x-table>
        <thead><tr><th>{{ __('ui.tenants.name') }}</th><th>{{ __('ui.tenants.address') }}</th><th>{{ __('ui.tenants.level') }}</th><th>{{ __('ui.nav.users') }}</th><th>{{ __('ui.users.status') }}</th><th class="sm:text-right">{{ __('ui.users.actions') }}</th></tr></thead>
        <tbody>
            @forelse ($tenants as $t)
                <tr wire:key="tenant-{{ $t->id }}">
                    <td data-label="{{ __('ui.tenants.name') }}" class="font-medium">{{ $t->name }}</td>
                    <td data-label="{{ __('ui.tenants.address') }}"><a href="{{ $t->url() }}" class="text-primary underline">/{{ $t->slug }}</a></td>
                    <td data-label="{{ __('ui.tenants.level') }}">{{ $t->isCampus() ? __('ui.tenants.campus') : __('ui.tenants.school') }}</td>
                    <td data-label="{{ __('ui.nav.users') }}">{{ $t->users_count }}</td>
                    <td data-label="{{ __('ui.users.status') }}"><x-badge :variant="$t->isActive() ? 'success' : 'danger'">{{ $t->isActive() ? __('ui.tenants.active') : __('ui.tenants.suspended') }}</x-badge></td>
                    <td data-label="{{ __('ui.users.actions') }}" class="sm:text-right">
                        <x-button variant="secondary" size="sm" wire:click="toggleStatus({{ $t->id }})" :icon="$t->isActive() ? 'ban' : 'check'">
                            {{ $t->isActive() ? __('ui.tenants.suspend') : __('ui.tenants.activate') }}
                        </x-button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty-state icon="building-2" /></td></tr>
            @endforelse
        </tbody>
    </x-table>
</div>
