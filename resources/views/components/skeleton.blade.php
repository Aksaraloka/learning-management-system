@props(['lines' => 1])

<div {{ $attributes->class('space-y-2') }} role="status" aria-label="{{ __('ui.loading') }}">
    @for ($i = 0; $i < $lines; $i++)
        <div class="skeleton h-4 {{ $i === $lines - 1 && $lines > 1 ? 'w-2/3' : 'w-full' }}"></div>
    @endfor
</div>
