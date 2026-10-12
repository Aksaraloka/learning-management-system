@props(['title' => null, 'padding' => true])

<section {{ $attributes->class('rounded-card border border-border bg-surface') }}>
    @if ($title || isset($actions))
        <header class="flex items-center justify-between gap-3 border-b border-border px-4 py-3 sm:px-5">
            @if ($title) <h2 class="text-base font-semibold">{{ $title }}</h2> @endif
            @isset($actions) <div class="flex items-center gap-2">{{ $actions }}</div> @endisset
        </header>
    @endif

    <div @class(['p-4 sm:p-5' => $padding])>{{ $slot }}</div>
</section>
