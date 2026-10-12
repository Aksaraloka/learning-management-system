{{-- Wadah toast global; ditaruh sekali di layout. Lihat resources/js/lib/toast.js. --}}
<div class="pointer-events-none fixed inset-x-0 bottom-20 z-[60] flex flex-col items-center gap-2 px-4 lg:bottom-6 lg:items-end"
     role="region" aria-live="polite" aria-label="{{ __('ui.nav.notifications') }}">
    <template x-for="toast in $store.toasts.items" :key="toast.id">
        <div x-transition class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-lg border border-border bg-surface p-3 shadow-sm" role="status">
            <span class="mt-0.5" :class="{ 'text-success': toast.type === 'success', 'text-danger': toast.type === 'error', 'text-warning': toast.type === 'warning', 'text-primary': toast.type === 'info' }">
                <x-icon name="info" class="size-5" />
            </span>
            <p class="flex-1 text-sm" x-text="toast.message"></p>
            <button type="button" class="-m-1 grid size-8 place-items-center rounded-md text-text-muted hover:bg-surface-muted" @click="$store.toasts.remove(toast.id)">
                <x-icon name="x" class="size-4" />
                <span class="sr-only">{{ __('ui.notifications_dismiss') }}</span>
            </button>
        </div>
    </template>
</div>
