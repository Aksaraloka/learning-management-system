@props(['name' => null, 'label' => null, 'hint' => null])

@php
    $id = $attributes->get('id', $name ?? 'field-'.uniqid());
    $error = $name ? $errors->first($name) : null;
@endphp

<x-field :id="$id" :label="$label" :hint="$hint" :error="$error">
    <textarea
        id="{{ $id }}"
        rows="4"
        @if ($name) name="{{ $name }}" @endif
        @if ($error) aria-invalid="true" @endif
        aria-describedby="{{ $error ? $id.'-error' : ($hint ? $id.'-hint' : '') }}"
        {{ $attributes->except('id')->class('input') }}
    >{{ $slot }}</textarea>
</x-field>
