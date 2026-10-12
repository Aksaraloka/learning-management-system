{{-- variant="icon" (navbar, dropdown) atau "segmented" (halaman profil / /ui). --}}
@props(['variant' => 'icon'])

@php
    $options = ['light' => 'sun', 'dark' => 'moon', 'system' => 'monitor'];
@endphp

@if ($variant === 'segmented')
    <div role="radiogroup" aria-label="{{ __('ui.theme.label') }}" class="inline-flex rounded-lg border border-border bg-surface p-1">
        @foreach ($options as $key => $icon)
            <button type="button" role="radio" @click="$store.theme.set('{{ $key }}')"
                    :aria-checked="$store.theme.choice === '{{ $key }}'"
                    :class="$store.theme.choice === '{{ $key }}' ? 'bg-primary text-primary-fg' : 'text-text-muted hover:bg-surface-muted'"
                    class="inline-flex min-h-9 items-center gap-2 rounded-md px-3 text-sm font-medium">
                <x-icon :name="$icon" class="size-4" /> {{ __('ui.theme.'.$key) }}
            </button>
        @endforeach
    </div>
@else
    <x-dropdown :label="__('ui.theme.label')">
        <x-slot:trigger>
            <x-icon name="sun" class="size-5 dark:hidden" />
            <x-icon name="moon" class="hidden size-5 dark:block" />
        </x-slot:trigger>

        @foreach ($options as $key => $icon)
            <button type="button" role="menuitemradio" @click="$store.theme.set('{{ $key }}')"
                    :aria-checked="$store.theme.choice === '{{ $key }}'"
                    class="flex min-h-10 w-full items-center gap-2 rounded-md px-3 text-left text-sm hover:bg-surface-muted"
                    :class="{ 'text-primary font-semibold': $store.theme.choice === '{{ $key }}' }">
                <x-icon :name="$icon" class="size-4" /> {{ __('ui.theme.'.$key) }}
            </button>
        @endforeach
    </x-dropdown>
@endif
