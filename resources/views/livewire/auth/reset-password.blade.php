<x-card>
    <h1 class="text-xl font-semibold">{{ __('ui.auth.reset_title') }}</h1>

    <form wire:submit="reset_password" class="mt-5 space-y-4">
        <x-input name="email" type="email" wire:model="email" label="Email" autocomplete="email" required />
        <x-input name="password" type="password" wire:model="password" :label="__('ui.auth.new_password')" autocomplete="new-password" required />
        <x-input name="password_confirmation" type="password" wire:model="password_confirmation" :label="__('ui.auth.confirm_password')" autocomplete="new-password" required />
        <x-button type="submit" class="w-full" wire:loading.attr="disabled">{{ __('ui.actions.save') }}</x-button>
    </form>
</x-card>
