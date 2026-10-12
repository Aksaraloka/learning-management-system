@props(['name' => null, 'label' => null, 'hint' => null, 'time' => false])

@php
    $id = $attributes->get('id', $name ?? 'field-'.uniqid());
    $error = $name ? $errors->first($name) : null;
@endphp

<x-field :id="$id" :label="$label" :hint="$hint" :error="$error">
    <div wire:ignore x-data="datePicker({ time: @js((bool) $time) })">
        <input
            x-ref="input"
            id="{{ $id }}"
            type="text"
            @if ($name) name="{{ $name }}" @endif
            @if ($error) aria-invalid="true" @endif
            {{ $attributes->except('id')->class('input') }}
        >
    </div>
</x-field>
