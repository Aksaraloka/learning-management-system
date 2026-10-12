@props(['name' => null, 'label' => null, 'hint' => null, 'type' => 'text'])

@php
    $id = $attributes->get('id', $name ?? 'field-'.uniqid());
    $error = $name ? $errors->first($name) : null;
@endphp

<x-field :id="$id" :label="$label" :hint="$hint" :error="$error">
    <input
        id="{{ $id }}"
        type="{{ $type }}"
        @if ($name) name="{{ $name }}" @endif
        @if ($error) aria-invalid="true" @endif
        aria-describedby="{{ $error ? $id.'-error' : ($hint ? $id.'-hint' : '') }}"
        {{ $attributes->except('id')->class('input') }}
    >
</x-field>
