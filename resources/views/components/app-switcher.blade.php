@props(['variant' => 'dropdown'])
@php
    $icons = ['academic' => 'academic-cap', 'lms' => 'book-open', 'admission' => 'user-plus'];
    $switcher = app(\App\Support\Academic\AppSwitcher::class);
    $apps = $switcher->linksFor(auth()->user());
@endphp
{{-- Tanpa integrasi Academic tidak ada aplikasi lain yang perlu dialihkan. --}}
@if (count($apps) > 1)
    @if ($variant === 'list')
        {{-- Di HP: bagian dari menu akun, bukan bar bawah. --}}
        <div class="lg:hidden" role="group" aria-labelledby="app-switcher-title">
            <p id="app-switcher-title" class="px-3 pb-1 pt-2 text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('ui.apps.title') }}</p>
            @foreach ($apps as $app)
                @if ($app['key'] === 'lms')
                    <p class="flex min-h-11 items-center gap-2 rounded-md px-3 text-sm font-semibold text-primary" aria-current="true">
                        <x-icon :name="$icons[$app['key']]" class="size-4" /> {{ $app['label'] }} <span class="sr-only">({{ __('ui.apps.current') }})</span>
                    </p>
                @else
                    <a href="{{ $switcher->href($app) }}" role="menuitem" class="flex min-h-11 w-full items-center gap-2 rounded-md px-3 text-sm hover:bg-surface-muted">
                        <x-icon :name="$icons[$app['key']] ?? 'squares-2x2'" class="size-4" /> {{ $app['label'] }}
                    </a>
                @endif
            @endforeach
            <div class="my-1 border-t border-border" role="separator"></div>
        </div>
    @else
        <div x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false" class="relative hidden lg:block">
            <button type="button" @click="open = !open" aria-haspopup="menu" :aria-expanded="open"
                class="flex size-11 items-center justify-center rounded-lg text-text-muted hover:bg-surface-muted hover:text-text">
                <x-icon name="squares-2x2" /><span class="sr-only">{{ __('ui.apps.title') }}</span>
            </button>
            <div x-show="open" x-cloak x-transition.opacity role="menu" aria-label="{{ __('ui.apps.title') }}"
                class="absolute right-0 z-40 mt-1 min-w-56 rounded-lg border border-border bg-surface p-1 shadow-sm">
                <p class="px-3 pb-1 pt-2 text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('ui.apps.title') }}</p>
                @foreach ($apps as $app)
                    @if ($app['key'] === 'lms')
                        <p class="flex min-h-11 items-center gap-2 rounded-md px-3 text-sm font-semibold text-primary" aria-current="true">
                            <x-icon :name="$icons[$app['key']]" class="size-4" /> {{ $app['label'] }}
                            <x-badge variant="primary" class="ml-auto">{{ __('ui.apps.opened') }}</x-badge>
                        </p>
                    @else
                        <a href="{{ $switcher->href($app) }}" role="menuitem" class="flex min-h-11 w-full items-center gap-2 rounded-md px-3 text-sm hover:bg-surface-muted">
                            <x-icon :name="$icons[$app['key']] ?? 'squares-2x2'" class="size-4" /> {{ $app['label'] }}
                        </a>
                    @endif
                @endforeach
            </div>
        </div>
    @endif
@endif
