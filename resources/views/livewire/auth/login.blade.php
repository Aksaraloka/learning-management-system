<x-card>
    <h1 class="text-xl font-semibold">{{ __('ui.auth.login_title') }}</h1>

    @unless (app()->bound('tenant'))
        <p class="mt-3 rounded-lg bg-surface-muted p-3 text-sm text-text-muted" role="note">
            {{ __('ui.auth.central_hint', ['example' => 'namasekolah.'.config('tenancy.root_domain')]) }}
        </p>
    @endunless

    <form wire:submit="login" class="mt-5 space-y-4">
        <x-input name="identifier" wire:model="identifier" :label="__('ui.auth.identifier')" autocomplete="username" autocapitalize="none" required autofocus />
        <x-input name="password" type="password" wire:model="password" :label="__('ui.auth.password')" autocomplete="current-password" required />

        <label class="flex min-h-11 items-center gap-3 text-sm">
            <input type="checkbox" wire:model="remember" class="size-5 rounded border-border accent-[var(--color-primary)]">
            {{ __('ui.auth.remember') }}
        </label>

        <x-button type="submit" class="w-full" wire:loading.attr="disabled">{{ __('ui.auth.login') }}</x-button>

        <p class="text-center text-sm">
            <a href="{{ route('password.request') }}" class="text-primary underline">{{ __('ui.auth.forgot') }}</a>
        </p>
    </form>
</x-card>
