@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-layouts.head :title="$title" />
</head>
<body>
    <a href="#content" class="sr-only z-[70] rounded-lg bg-primary px-4 py-2 text-primary-fg focus:not-sr-only focus:fixed focus:left-4 focus:top-4">{{ __('ui.nav.skip') }}</a>

    <div class="flex min-h-dvh flex-col">
        <header class="flex items-center justify-between px-4 py-4 sm:px-6">
            <a href="{{ route('home') }}" class="flex items-center gap-2 font-bold">
                <x-icon name="graduation-cap" class="size-6 text-primary" /> {{ tenant_name() }}
            </a>
            <x-theme-toggle />
        </header>

        <main id="content" class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center px-4 pb-12">
            {{ $slot }}
        </main>

        <footer class="px-4 pb-6 text-center text-sm text-text-muted">
            <a href="{{ route('privacy') }}" class="underline">{{ __('ui.privacy.title') }}</a>
        </footer>
    </div>

    <x-toast />
    @livewireScriptConfig
</body>
</html>
