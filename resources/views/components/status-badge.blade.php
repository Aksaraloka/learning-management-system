{{-- Status tugas: not_submitted | submitted | late | graded | returned. Warna selalu sama di seluruh aplikasi. --}}
@props(['status'])

@php
    $map = [
        'not_submitted' => ['neutral', 'circle'],
        'submitted' => ['info', 'upload'],
        'late' => ['warning', 'clock'],
        'graded' => ['success', 'circle-check'],
        'returned' => ['danger', 'refresh-cw'],
    ];
    [$variant, $icon] = $map[$status];
@endphp

<x-badge :variant="$variant" {{ $attributes }}>
    <x-icon :name="$icon" class="size-3.5" solid />
    {{ __('ui.status.'.$status) }}
</x-badge>
