{{-- Slot `trigger` = isi tombol; slot default = item (<a>/<button role="menuitem">). --}}
@props(['align' => 'right', 'label' => null])

<div x-data="{ open: false }" @keydown.escape="open = false" @click.outside="open = false" class="relative">
    <button type="button" @click="open = !open" :aria-expanded="open" aria-haspopup="menu"
            @if ($label) aria-label="{{ $label }}" @endif
            class="inline-flex min-h-11 items-center gap-2 rounded-lg px-2 hover:bg-surface-muted">
        {{ $trigger }}
    </button>

    <div x-show="open" x-cloak x-transition.opacity role="menu" @click="open = false"
         @class(['absolute z-40 mt-1 min-w-48 rounded-lg border border-border bg-surface p-1 shadow-sm', 'right-0' => $align === 'right', 'left-0' => $align === 'left'])>
        {{ $slot }}
    </div>
</div>
