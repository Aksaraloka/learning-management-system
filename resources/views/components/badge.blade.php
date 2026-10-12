@props(['variant' => 'neutral'])

@php
    $variants = [
        'neutral' => 'bg-surface-muted text-text-muted',
        'primary' => 'bg-primary/15 text-primary',
        'success' => 'bg-success/15 text-success',
        'warning' => 'bg-warning/15 text-warning',
        'danger' => 'bg-danger/15 text-danger',
    ];
@endphp

<span {{ $attributes->class('inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold '.$variants[$variant]) }}>{{ $slot }}</span>
