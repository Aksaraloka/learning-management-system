<x-card>
    <h1 class="text-xl font-semibold">{{ __('ui.auth.forgot_title') }}</h1>

    @if ($sent)
        <p class="mt-4 text-sm" role="status">{{ __('ui.auth.link_sent') }}</p>
    @else
        <p class="mt-2 text-sm text-text-muted">{{ __('ui.auth.forgot_hint') }}</p>

        <form wire:submit="send" class="mt-5 space-y-4">
            <x-input name="email" type="email" wire:model="email" label="Email" autocomplete="email" required />
            <x-button type="submit" class="w-full" wire:loading.attr="disabled">{{ __('ui.auth.send_link') }}</x-button>
        </form>
    @endif

    <p class="mt-4 text-center text-sm"><a href="{{ route('login') }}" class="text-primary underline">{{ __('ui.auth.back_to_login') }}</a></p>
</x-card>
