{{-- Status tugas: not_submitted | submitted | late | graded. Warna selalu sama di seluruh aplikasi. --}}
@props(['status'])

@php
    $map = [
        'not_submitted' => ['neutral', 'circle'],
        'submitted' => ['primary', 'upload'],
        'late' => ['warning', 'clock'],
        'graded' => ['success', 'circle-check'],
    ];
    [$variant, $icon] = $map[$status];
@endphp

<x-badge :variant="$variant" {{ $attributes }}>
    <x-icon :name="$icon" class="size-3" />
    {{ __('ui.status.'.$status) }}
</x-badge>
