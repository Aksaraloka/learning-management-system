@props(['title' => null])

{{-- Layout halaman masuk: panel merek merah di kiri (di HP menjadi header + info di bawah formulir), formulir di kanan. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-layouts.head :title="$title" />
</head>
<body>
    <a href="#content" class="sr-only z-[70] rounded-lg bg-primary px-4 py-2 text-primary-fg focus:not-sr-only focus:fixed focus:left-4 focus:top-4">{{ __('ui.nav.skip') }}</a>

    <div class="grid min-h-dvh lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        {{-- Panel merek --}}
        <aside class="relative overflow-hidden bg-brand text-brand-fg lg:flex lg:flex-col lg:justify-center" aria-label="{{ institution_name() }}">
            {{-- Lingkaran dekoratif --}}
            <span aria-hidden="true" class="pointer-events-none absolute -left-24 -top-24 size-72 rounded-full bg-white/10"></span>
            <span aria-hidden="true" class="pointer-events-none absolute -bottom-40 right-[-6rem] hidden size-[28rem] rounded-full bg-white/10 lg:block"></span>
            <span aria-hidden="true" class="pointer-events-none absolute bottom-24 right-[-8rem] hidden size-72 rounded-full bg-white/5 lg:block"></span>

            <div class="relative mx-auto w-full max-w-lg px-6 py-8 lg:px-12 lg:py-16">
                <div class="flex items-center gap-4">
                    <span class="grid size-14 shrink-0 place-items-center rounded-xl bg-white text-brand shadow-sm lg:size-16">
                        <x-icon name="academic-cap" class="size-8 lg:size-9" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-2xl font-bold leading-tight lg:text-3xl">{{ __('ui.app.name') }}</p>
                        <p class="text-sm text-brand-fg/80">{{ __('ui.auth.panel_subtitle') }}</p>
                    </div>
                </div>

                <p class="mt-4 text-sm font-medium text-brand-fg/90 lg:mt-6">{{ institution_name() }}</p>

                <div class="mt-8 hidden lg:block">
                    <h2 class="text-3xl font-bold">{{ __('ui.auth.panel_welcome') }}</h2>
                    <ul class="mt-5 list-disc space-y-3 pl-5 text-base leading-relaxed text-brand-fg/95 marker:text-brand-fg/60">
                        @foreach (trans('ui.auth.panel_points') as $point)
                            <li>{{ str_replace(':institution', institution_name(), $point) }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </aside>

        {{-- Formulir --}}
        <div class="flex min-h-0 flex-col bg-surface">
            <header class="flex items-center justify-end px-4 py-3 sm:px-8 lg:py-5">
                <x-theme-toggle />
            </header>

            <main id="content" class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center px-6 py-6 sm:px-8">
                {{ $slot }}

                <p class="mt-8 text-center text-sm text-text-muted">{{ __('ui.auth.no_account') }}</p>

                {{-- Info yang di desktop ada di panel kiri; di HP tampil di bawah formulir. --}}
                <section class="mt-8 rounded-card border border-border bg-surface-muted p-4 lg:hidden" aria-labelledby="auth-info">
                    <h2 id="auth-info" class="text-sm font-semibold">{{ __('ui.auth.panel_welcome') }}</h2>
                    <ul class="mt-2 list-disc space-y-1.5 pl-5 text-sm text-text-muted">
                        @foreach (trans('ui.auth.panel_points') as $point)
                            <li>{{ str_replace(':institution', institution_name(), $point) }}</li>
                        @endforeach
                    </ul>
                </section>
            </main>

            <footer class="flex flex-wrap items-center justify-between gap-x-6 gap-y-1 border-t border-border px-6 py-4 text-sm text-text-muted sm:px-8">
                <span>© {{ now()->year }} {{ institution_name() }}</span>
                <a href="{{ route('privacy') }}" class="underline">{{ __('ui.privacy.title') }}</a>
            </footer>
        </div>
    </div>

    <x-toast />
    @livewireScriptConfig
</body>
</html>
