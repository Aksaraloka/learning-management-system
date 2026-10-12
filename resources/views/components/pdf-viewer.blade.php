@props(['url'])

<div x-data="pdfViewer({ url: @js($url) })" {{ $attributes->class('rounded-card border border-border bg-surface') }}>
    <div class="flex items-center justify-between gap-2 border-b border-border p-2">
        <button type="button" @click="go(-1)" :disabled="page <= 1" class="grid size-10 place-items-center rounded-lg hover:bg-surface-muted disabled:opacity-40">
            <x-icon name="chevron-left" /><span class="sr-only">{{ __('ui.pdf.prev') }}</span>
        </button>
        <span class="text-sm text-text-muted" x-text="pages ? `${page} / ${pages}` : ''"></span>
        <button type="button" @click="go(1)" :disabled="page >= pages" class="grid size-10 place-items-center rounded-lg hover:bg-surface-muted disabled:opacity-40">
            <x-icon name="chevron-right" /><span class="sr-only">{{ __('ui.pdf.next') }}</span>
        </button>
    </div>

    <div x-ref="wrap" class="p-2">
        <div x-show="loading" class="skeleton h-64 w-full"></div>
        <p x-show="failed" x-cloak class="p-4 text-center text-sm text-danger">{{ __('ui.pdf.failed') }}</p>
        <canvas x-ref="canvas" x-show="!loading && !failed" class="mx-auto max-w-full"></canvas>
    </div>
</div>
