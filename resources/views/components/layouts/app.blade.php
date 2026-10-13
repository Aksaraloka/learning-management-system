@props(['title' => null, 'nav' => null])

@php
    // Rute yang belum dibuat (fase berikutnya) dirender sebagai `#`.
    $nav ??= \App\Support\Navigation::for(auth()->user());
    $user = auth()->user();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-layouts.head :title="$title" />
</head>
<body x-data="{ collapsed: $persist(false).as('sidebar-collapsed') }" data-auth="{{ $user ? '1' : '0' }}">
    <a href="#content" class="sr-only z-[70] rounded-lg bg-primary px-4 py-2 text-primary-fg focus:not-sr-only focus:fixed focus:left-4 focus:top-4">{{ __('ui.nav.skip') }}</a>

    {{-- Sidebar desktop --}}
    <aside class="fixed inset-y-0 left-0 z-30 hidden flex-col border-r border-border bg-surface transition-[width] lg:flex"
           :class="collapsed ? 'w-[4.5rem]' : 'w-64'">
        <div class="flex h-16 items-center gap-3 border-b border-border px-5">
            <x-icon name="graduation-cap" class="size-6 shrink-0 text-primary" />
            <span class="truncate text-lg font-bold" x-show="!collapsed">{{ institution_name() }}</span>
        </div>

        <nav class="flex-1 space-y-1 p-3" aria-label="{{ __('ui.nav.main') }}">
            @foreach ($nav as $item)
                @php($url = Route::has($item['route']) ? route($item['route']) : '#')
                @php($active = Route::has($item['route']) && request()->routeIs($item['match'] ?? $item['route']))
                <a href="{{ $url }}" @if ($active) aria-current="page" @endif title="{{ $item['label'] }}"
                   @class([
                       'flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium',
                       'bg-primary/15 text-primary' => $active,
                       'text-text-muted hover:bg-surface-muted hover:text-text' => ! $active,
                   ])>
                    <span class="relative">
                        <x-icon :name="$item['icon']" />
                        @if (($item['badge'] ?? 0) > 0)
                            <span class="absolute -right-1.5 -top-1.5 grid min-w-4 place-items-center rounded-full bg-primary px-1 text-[10px] font-bold leading-4 text-primary-fg"
                                  aria-label="{{ __('notifications.unread_count', ['n' => $item['badge']]) }}">{{ $item['badge'] > 99 ? '99+' : $item['badge'] }}</span>
                        @endif
                    </span>
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
            <div class="flex min-w-0 items-center gap-2 lg:hidden">
                <x-icon name="graduation-cap" class="size-6 shrink-0 text-primary" />
                <span class="truncate font-bold">{{ institution_name() }}</span>
            </div>
            <h1 class="hidden truncate text-lg font-semibold lg:block">{{ $title }}</h1>

            <div class="flex items-center gap-1">
                <x-theme-toggle />

                @if ($user)
                    <x-dropdown :label="$user->name">
                        <x-slot:trigger>
                            <span class="grid size-8 place-items-center rounded-full bg-primary/15 text-sm font-semibold text-primary">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                            <span class="hidden max-w-32 truncate text-sm sm:block">{{ $user->name }}</span>
                        </x-slot:trigger>

                        <a href="{{ route('profile.edit') }}" role="menuitem" class="flex min-h-10 items-center gap-2 rounded-md px-3 text-sm hover:bg-surface-muted"><x-icon name="user" class="size-4" /> {{ __('ui.nav.profile') }}</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" role="menuitem" class="flex min-h-10 w-full items-center gap-2 rounded-md px-3 text-left text-sm hover:bg-surface-muted"><x-icon name="log-out" class="size-4" /> {{ __('ui.auth.logout') }}</button>
                        </form>
                    </x-dropdown>
                @endif
            </div>
        </header>

        <main id="content" class="mx-auto max-w-6xl px-4 pb-28 pt-6 sm:px-6 lg:pb-10">
            @if ($title) <h1 class="mb-4 text-xl font-semibold lg:hidden">{{ $title }}</h1> @endif
            {{ $slot }}
        </main>
    </div>

    {{-- Bottom navigation HP --}}
    <nav class="fixed inset-x-0 bottom-0 z-30 grid border-t border-border bg-surface pb-[env(safe-area-inset-bottom)] lg:hidden"
         style="grid-template-columns: repeat({{ count($nav) }}, minmax(0, 1fr))" aria-label="{{ __('ui.nav.main') }}">
        @foreach ($nav as $item)
            @php($url = Route::has($item['route']) ? route($item['route']) : '#')
            @php($active = Route::has($item['route']) && request()->routeIs($item['match'] ?? $item['route']))
            <a href="{{ $url }}" @if ($active) aria-current="page" @endif
               @class([
                   'flex min-h-14 flex-col items-center justify-center gap-0.5 text-xs font-medium',
                   'text-primary' => $active,
                   'text-text-muted' => ! $active,
               ])>
                <span class="relative">
                    <x-icon :name="$item['icon']" />
                    @if (($item['badge'] ?? 0) > 0)
                        <span class="absolute -right-2 -top-1.5 grid min-w-4 place-items-center rounded-full bg-primary px-1 text-[10px] font-bold leading-4 text-primary-fg"
                              aria-label="{{ __('notifications.unread_count', ['n' => $item['badge']]) }}">{{ $item['badge'] > 99 ? '99+' : $item['badge'] }}</span>
                    @endif
                </span>
                {{ $item['label'] }}
            </a>
        @endforeach
    </nav>

    <x-toast />
    @livewireScriptConfig
</body>
</html>
