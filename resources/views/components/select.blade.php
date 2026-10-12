@props(['name' => null, 'label' => null, 'hint' => null, 'options' => [], 'placeholder' => null])

@php
    $id = $attributes->get('id', $name ?? 'field-'.uniqid());
    $error = $name ? $errors->first($name) : null;
@endphp

<x-field :id="$id" :label="$label" :hint="$hint" :error="$error">
    <select
        id="{{ $id }}"
        @if ($name) name="{{ $name }}" @endif
        @if ($error) aria-invalid="true" @endif
        aria-describedby="{{ $error ? $id.'-error' : ($hint ? $id.'-hint' : '') }}"
        {{ $attributes->except('id')->class('input') }}
    >
        @if ($placeholder) <option value="">{{ $placeholder }}</option> @endif
        @foreach ($options as $value => $text)
            <option value="{{ $value }}">{{ $text }}</option>
        @endforeach
        {{ $slot }}
    </select>
</x-field>
