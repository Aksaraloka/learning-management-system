<div>
    <h1 class="text-3xl font-bold">{{ __('ui.auth.forgot_title') }}</h1>

    @if ($sent)
        <p class="mt-4 rounded-lg bg-surface-muted p-3 text-sm" role="status">{{ __('ui.auth.link_sent') }}</p>
    @else
        <p class="mt-1 text-sm text-text-muted">{{ __('ui.auth.forgot_hint') }}</p>

        <form wire:submit="send" class="mt-8 space-y-4">
            <x-input name="email" type="email" wire:model="email" label="Email" autocomplete="email" required />
            <x-button type="submit" size="lg" class="w-full" wire:loading.attr="disabled">{{ __('ui.auth.send_link') }}</x-button>
        </form>
    @endif

    <p class="mt-6 text-center text-sm"><a href="{{ route('login') }}" class="inline-flex min-h-11 items-center gap-1 text-primary underline"><x-icon name="arrow-left" class="size-4" /> {{ __('ui.auth.back_to_login') }}</a></p>
</div>
