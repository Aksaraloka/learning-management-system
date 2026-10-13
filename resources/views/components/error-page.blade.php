@props(['code', 'icon' => 'exclamation-triangle'])

<x-layouts.guest :title="__('errors.'.$code.'.title')">
    <div class="text-center">
        <span class="mx-auto grid size-14 place-items-center rounded-full bg-primary/15 text-primary"><x-icon :name="$icon" class="size-7" /></span>
        <p class="mt-4 text-sm font-semibold text-text-muted">{{ $code }}</p>
        <h1 class="mt-1 text-2xl font-semibold">{{ __('errors.'.$code.'.title') }}</h1>
        <p class="mt-2 text-text-muted">{{ __('errors.'.$code.'.message') }}</p>

        <div class="mt-6 flex flex-wrap justify-center gap-2">
            <x-button :href="route('home')">{{ __('errors.home') }}</x-button>
            <x-button variant="secondary" onclick="history.length > 1 ? history.back() : location.assign('/')">{{ __('errors.back') }}</x-button>
        </div>
    </div>
</x-layouts.guest>
