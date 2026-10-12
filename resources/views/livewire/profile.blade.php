<div class="mx-auto max-w-2xl space-y-6">
    <x-card :title="__('ui.profile.info')">
        <dl class="mb-4 grid gap-3 text-sm sm:grid-cols-2">
            @if ($user->identity_number)
                <div><dt class="text-text-muted">{{ term('identity') }}</dt><dd class="font-medium">{{ $user->identity_number }}</dd></div>
            @endif
            @if ($user->email)
                <div><dt class="text-text-muted">Email</dt><dd class="font-medium">{{ $user->email }}</dd></div>
            @endif
            <div class="sm:col-span-2">
                <dt class="text-text-muted">{{ __('ui.profile.roles') }}</dt>
                <dd class="mt-1 flex flex-wrap gap-1">
                    @foreach ($user->roles as $role)
                        <x-badge variant="primary">{{ \App\Enums\Role::tryFrom($role->name)?->label() ?? $role->name }}</x-badge>
                    @endforeach
                </dd>
            </div>
        </dl>

        <form wire:submit="saveInfo" class="space-y-4">
            <x-input name="name" wire:model="name" :label="__('ui.profile.name')" required />
            <x-button type="submit">{{ __('ui.actions.save') }}</x-button>
        </form>
    </x-card>

    <x-card :title="__('ui.theme.label')">
        <x-theme-toggle variant="segmented" />
    </x-card>

    <x-card :title="__('ui.profile.password_title')">
        <form wire:submit="savePassword" class="space-y-4">
            <x-input name="current_password" type="password" wire:model="current_password" :label="__('ui.profile.current_password')" autocomplete="current-password" />
            <x-input name="password" type="password" wire:model="password" :label="__('ui.auth.new_password')" autocomplete="new-password" />
            <x-input name="password_confirmation" type="password" wire:model="password_confirmation" :label="__('ui.auth.confirm_password')" autocomplete="new-password" />
            <x-button type="submit">{{ __('ui.actions.save') }}</x-button>
        </form>
    </x-card>
</div>
