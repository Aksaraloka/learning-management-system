<x-layouts.app :title="__('ui.nav.home')">
    <x-card>
        <div class="py-6 text-center">
            <x-icon name="graduation-cap" class="mx-auto size-10 text-primary" />
            <h2 class="mt-3 text-2xl font-bold">{{ config('app.name') }}</h2>
            <p class="mt-1 text-text-muted">{{ __('ui.app.tagline') }}</p>
        </div>
    </x-card>
</x-layouts.app>
