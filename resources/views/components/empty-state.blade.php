@props(['icon' => 'inbox', 'title' => null, 'description' => null])

<div {{ $attributes->class('flex flex-col items-center px-4 py-10 text-center') }}>
    <span class="grid size-12 place-items-center rounded-full bg-surface-muted text-text-muted">
        <x-icon :name="$icon" class="size-6" />
    </span>
    <h3 class="mt-3 text-base font-semibold">{{ $title ?? __('ui.empty.title') }}</h3>
    <p class="mt-1 max-w-sm text-sm text-text-muted">{{ $description ?? __('ui.empty.description') }}</p>
    @if (trim($slot)) <div class="mt-4">{{ $slot }}</div> @endif
</div>
