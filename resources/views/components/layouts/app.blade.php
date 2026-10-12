@props(['title' => null])

@php
    // Placeholder `#` sampai route fitur terkait dibuat di fase berikutnya.
    $nav = [
        ['label' => __('ui.nav.home'), 'icon' => 'home', 'route' => 'home'],
        ['label' => __('ui.nav.classes'), 'icon' => 'book-open', 'route' => 'courses.index'],
        ['label' => __('ui.nav.tasks'), 'icon' => 'clipboard-list', 'route' => 'assignments.index'],
        ['label' => __('ui.nav.notifications'), 'icon' => 'bell', 'route' => 'notifications.index'],
        ['label' => __('ui.nav.profile'), 'icon' => 'user', 'route' => 'profile.edit'],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    <meta name="theme-color" content="#4338ca">

    {{-- Pasang class `dark` sebelum CSS dimuat agar tidak berkedip. Kunci sama dengan Alpine persist. --}}
    <script>
        (function () {
            try {
                var t = JSON.parse(localStorage.getItem('_x_theme') || '"system"');
                var d = t === 'dark' || (t !== 'light' && matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', d);
                document.documentElement.style.colorScheme = d ? 'dark' : 'light';
            } catch (e) {}
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body x-data="{ collapsed: $persist(false).as('sidebar-collapsed') }">
    <a href="#content" class="sr-only z-[70] rounded-lg bg-primary px-4 py-2 text-primary-fg focus:not-sr-only focus:fixed focus:left-4 focus:top-4">{{ __('ui.nav.skip') }}</a>

    {{-- Sidebar desktop --}}
    <aside class="fixed inset-y-0 left-0 z-30 hidden flex-col border-r border-border bg-surface transition-[width] lg:flex"
           :class="collapsed ? 'w-[4.5rem]' : 'w-64'">
        <div class="flex h-16 items-center gap-3 border-b border-border px-5">
            <x-icon name="graduation-cap" class="size-6 text-primary" />
            <span class="text-lg font-bold" x-show="!collapsed">{{ config('app.name') }}</span>
        </div>

        <nav class="flex-1 space-y-1 p-3" aria-label="{{ __('ui.nav.main') }}">
            @foreach ($nav as $item)
                @php($url = Route::has($item['route']) ? route($item['route']) : '#')
                @php($active = Route::has($item['route']) && request()->routeIs($item['route']))
                <a href="{{ $url }}" @if ($active) aria-current="page" @endif title="{{ $item['label'] }}"
                   @class([
                       'flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium',
                       'bg-primary/15 text-primary' => $active,
                       'text-text-muted hover:bg-surface-muted hover:text-text' => ! $active,
                   ])>
                    <x-icon :name="$item['icon']" />
                    <span x-show="!collapsed">{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>

        <button type="button" @click="collapsed = !collapsed" class="m-3 flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm text-text-muted hover:bg-surface-muted"
                :aria-label="collapsed ? '{{ __('ui.nav.expand') }}' : '{{ __('ui.nav.collapse') }}'">
            <x-icon name="panel-left-close" x-show="!collapsed" />
            <x-icon name="panel-left-open" x-show="collapsed" x-cloak />
            <span x-show="!collapsed">{{ __('ui.nav.collapse') }}</span>
        </button>
    </aside>

    <div class="transition-[padding] lg:pl-64" :class="collapsed && 'lg:!pl-[4.5rem]'">
        <header class="sticky top-0 z-20 flex h-16 items-center justify-between gap-3 border-b border-border bg-surface/90 px-4 backdrop-blur sm:px-6">
            <div class="flex items-center gap-2 lg:hidden">
                <x-icon name="graduation-cap" class="size-6 text-primary" />
                <span class="font-bold">{{ config('app.name') }}</span>
            </div>
            <h1 class="hidden truncate text-lg font-semibold lg:block">{{ $title }}</h1>

            <div class="flex items-center gap-1">
                <x-theme-toggle />
            </div>
        </header>

        <main id="content" class="mx-auto max-w-6xl px-4 pb-28 pt-6 sm:px-6 lg:pb-10">
            @if ($title) <h1 class="mb-4 text-xl font-semibold lg:hidden">{{ $title }}</h1> @endif
            {{ $slot }}
        </main>
    </div>

    {{-- Bottom navigation HP --}}
    <nav class="fixed inset-x-0 bottom-0 z-30 grid grid-cols-5 border-t border-border bg-surface pb-[env(safe-area-inset-bottom)] lg:hidden" aria-label="{{ __('ui.nav.main') }}">
        @foreach ($nav as $item)
            @php($url = Route::has($item['route']) ? route($item['route']) : '#')
            @php($active = Route::has($item['route']) && request()->routeIs($item['route']))
            <a href="{{ $url }}" @if ($active) aria-current="page" @endif
               @class([
                   'flex min-h-14 flex-col items-center justify-center gap-0.5 text-xs font-medium',
                   'text-primary' => $active,
                   'text-text-muted' => ! $active,
               ])>
                <x-icon :name="$item['icon']" />
                {{ $item['label'] }}
            </a>
        @endforeach
    </nav>

    <x-toast />
    @livewireScriptConfig
</body>
</html>
