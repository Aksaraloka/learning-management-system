{{-- Buka dengan: $dispatch('open-modal', 'nama') ; tutup dengan: $dispatch('close-modal', 'nama') atau Esc. --}}
@props(['name', 'title'])

<div x-data="{ open: false }"
     x-on:open-modal.window="if ($event.detail === '{{ $name }}') open = true"
     x-on:close-modal.window="if ($event.detail === '{{ $name }}') open = false"
     x-on:keydown.escape.window="open = false"
     x-show="open" x-cloak
     class="fixed inset-0 z-50 grid place-items-end sm:place-items-center"
     role="dialog" aria-modal="true" aria-labelledby="modal-{{ $name }}-title">
    <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/50" @click="open = false"></div>

    <div x-show="open" x-transition x-trap.noscroll.inert="open"
         class="relative w-full max-w-lg rounded-t-2xl border border-border bg-surface p-5 sm:rounded-card">
        <div class="mb-4 flex items-start justify-between gap-4">
            <h2 id="modal-{{ $name }}-title" class="text-lg font-semibold">{{ $title }}</h2>
            <button type="button" @click="open = false" class="-m-2 grid size-10 place-items-center rounded-lg text-text-muted hover:bg-surface-muted">
                <x-icon name="x" />
                <span class="sr-only">{{ __('ui.actions.close') }}</span>
            </button>
        </div>

        <div>{{ $slot }}</div>

        @isset($footer)
            <div class="mt-5 flex flex-wrap justify-end gap-2">{{ $footer }}</div>
        @endisset
    </div>
</div>
