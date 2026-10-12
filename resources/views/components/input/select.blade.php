@props(['name' => null, 'label' => null, 'hint' => null, 'options' => [], 'multiple' => false, 'placeholder' => null])

@php
    $id = $attributes->get('id', $name ?? 'field-'.uniqid());
    $error = $name ? $errors->first($name) : null;
    $config = ['multiple' => (bool) $multiple, 'placeholder' => $placeholder ?? __('ui.actions.choose'), 'noResults' => __('ui.empty.title')];
@endphp

<x-field :id="$id" :label="$label" :hint="$hint" :error="$error">
    {{-- Tom Select menambah DOM sendiri; wire:ignore mencegah Livewire menimpanya. --}}
    <div wire:ignore x-data="tomSelect(@js($config))">
        <select
            x-ref="select"
            id="{{ $id }}"
            @if ($name) name="{{ $name }}{{ $multiple ? '[]' : '' }}" @endif
            @if ($multiple) multiple @endif
            @if ($error) aria-invalid="true" @endif
            {{ $attributes->except('id')->class('input') }}
        >
            @unless ($multiple) <option value=""></option> @endunless
            @foreach ($options as $value => $text)
                <option value="{{ $value }}">{{ $text }}</option>
            @endforeach
            {{ $slot }}
        </select>
    </div>
</x-field>
