<div class="mx-auto max-w-2xl space-y-6">
    <a href="{{ route('profile.show') }}" wire:navigate class="inline-flex min-h-11 items-center gap-1 text-sm text-primary">
        <x-icon name="arrow-left" class="size-4" /> {{ __('ui.profile.title') }}
    </a>

    <x-card :title="__('ui.profile.edit_title')">
        <p class="mb-4 text-sm text-text-muted">{{ __('ui.profile.fixed_hint', ['identity' => term('identity')]) }}</p>

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

            <div class="flex flex-wrap gap-2">
                <x-button type="submit" icon="check" wire:loading.attr="disabled" wire:target="saveInfo,avatar">{{ __('ui.actions.save') }}</x-button>
                <x-button variant="secondary" :href="route('profile.show')" wire:navigate>{{ __('ui.actions.cancel') }}</x-button>
            </div>
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
