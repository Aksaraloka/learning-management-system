@props(['id', 'label' => null, 'hint' => null, 'error' => null])

<div {{ $attributes->class('space-y-1.5') }}>
    @if ($label)
        <label for="{{ $id }}" class="block text-sm font-medium text-text">{{ $label }}</label>
    @endif

    {{ $slot }}

    @if ($hint)
        <p id="{{ $id }}-hint" class="text-sm text-text-muted">{{ $hint }}</p>
    @endif

    @if ($error)
        <p id="{{ $id }}-error" class="text-sm text-danger" role="alert">{{ $error }}</p>
    @endif
</div>
