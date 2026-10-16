<form wire:submit="login" class="mt-8 space-y-4">
    <x-input name="identifier" wire:model="identifier" :label="__('ui.auth.identifier')" autocomplete="username" autocapitalize="none" :autofocus="$autofocus ?? false" required />
    <x-input name="password" type="password" wire:model="password" :label="__('ui.auth.password')" autocomplete="current-password" required />

    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
        <label class="flex min-h-11 items-center gap-3 text-sm">
            <input type="checkbox" wire:model="remember" class="size-5 rounded border-border accent-[var(--color-primary)]">
            {{ __('ui.auth.remember') }}
        </label>
        <a href="{{ route('password.request') }}" class="text-sm text-primary underline">{{ __('ui.auth.forgot') }}</a>
    </div>

    <x-button type="submit" size="lg" icon="arrow-right-end-on-rectangle" class="w-full" wire:loading.attr="disabled">{{ __('ui.auth.login') }}</x-button>
</form>
