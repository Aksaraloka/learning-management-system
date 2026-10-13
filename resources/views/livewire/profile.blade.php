<div class="mx-auto max-w-2xl space-y-6">
    <x-card :title="__('ui.profile.info')">
        <div class="mb-5 flex items-center gap-4">
            @if ($user->avatarUrl())
                <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}" class="size-20 shrink-0 rounded-full border border-border object-cover">
            @else
                <span class="grid size-20 shrink-0 place-items-center rounded-full bg-primary/15 text-3xl font-semibold text-primary" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
            @endif
            <div class="min-w-0">
                <p class="truncate text-lg font-semibold">{{ $user->name }}</p>
                @if ($user->identity_number) <p class="text-sm text-text-muted">{{ term('identity') }} {{ $user->identity_number }}</p> @endif
            </div>
        </div>

        <dl class="mb-4 grid gap-3 text-sm sm:grid-cols-2">
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

            <div class="grid gap-4 sm:grid-cols-2">
                <x-input name="email" type="email" wire:model="email" label="Email" autocomplete="email" :hint="$user->identity_number ? null : __('ui.profile.email_required')" />
                <x-input name="phone" type="tel" wire:model="phone" :label="__('ui.profile.phone')" autocomplete="tel" inputmode="tel" placeholder="0812…" />
            </div>

            <x-textarea name="bio" wire:model="bio" rows="3" :label="__('ui.profile.bio')" :hint="__('ui.profile.bio_hint')" maxlength="500" />

            <div class="space-y-2">
                <p class="text-sm font-medium">{{ __('ui.profile.photo') }}</p>
                @if ($user->hasAvatar())
                    <label class="flex min-h-11 items-center gap-3 text-sm">
                        <input type="checkbox" wire:model="removeAvatar" class="size-5 rounded border-border accent-[var(--color-primary)]">
                        {{ __('ui.profile.photo_remove') }}
                    </label>
                @endif
                <x-input.file name="avatar" livewire-prop="avatar" max-size="2MB" :accept="['image/jpeg', 'image/png', 'image/webp']" :hint="__('ui.profile.photo_hint')" />
            </div>

            <x-button type="submit" wire:loading.attr="disabled" wire:target="saveInfo,avatar">{{ __('ui.actions.save') }}</x-button>
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
