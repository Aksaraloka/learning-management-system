@props(['name' => null, 'label' => null, 'hint' => null, 'multiple' => false, 'accept' => [], 'maxSize' => '50MB', 'livewireProp' => null])

@php
    $id = $attributes->get('id', $name ?? 'field-'.uniqid());
    $error = $name ? $errors->first($name) : null;
    $config = ['multiple' => (bool) $multiple, 'accept' => array_values($accept) ?: null, 'maxSize' => $maxSize, 'livewireProp' => $livewireProp];
@endphp

<x-field :id="$id" :label="$label" :hint="$hint" :error="$error">
    <div wire:ignore x-data="filePicker(@js($config))">
        <input
            x-ref="input"
            id="{{ $id }}"
            type="file"
            @if ($name) name="{{ $name }}" @endif
            @if ($multiple) multiple @endif
            {{ $attributes->except('id') }}
        >
    </div>
</x-field>
