{{-- Untuk simplePaginate() di dalam komponen Livewire (WithPagination). --}}
@props(['paginator'])

@if ($paginator->hasPages())
    <nav class="mt-4 flex items-center justify-between gap-3" aria-label="Paginasi">
        <x-button variant="secondary" size="sm" wire:click="previousPage" :disabled="$paginator->onFirstPage()" icon="chevron-left">{{ __('pagination.previous') }}</x-button>
        <span class="text-sm text-text-muted">{{ __('ui.page', ['page' => $paginator->currentPage()]) }}</span>
        <x-button variant="secondary" size="sm" wire:click="nextPage" :disabled="! $paginator->hasMorePages()">{{ __('pagination.next') }}</x-button>
    </nav>
@endif
