<x-layouts.guest>
    <x-card>
        <div class="py-6 text-center">
            <x-icon name="graduation-cap" class="mx-auto size-10 text-primary" />
            <h1 class="mt-3 text-2xl font-bold">{{ config('app.name') }}</h1>
            <p class="mt-1 text-text-muted">{{ __('ui.app.tagline') }}</p>
            <div class="mt-6">
                <x-button :href="route('login')">{{ __('ui.auth.login') }}</x-button>
            </div>
        </div>
    </x-card>
</x-layouts.guest>
