@props(['name' => null, 'label' => null, 'hint' => null, 'time' => false, 'clearable' => false])

@php
    $id = $attributes->get('id', $name ?? 'field-'.uniqid());
    $error = $name ? $errors->first($name) : null;
@endphp

<x-field :id="$id" :label="$label" :hint="$hint" :error="$error">
    <div wire:ignore x-data="datePicker({ time: @js((bool) $time) })" class="flex items-center gap-2">
        <input
            x-ref="input"
            id="{{ $id }}"
            type="text"
            @if ($name) name="{{ $name }}" @endif
            @if ($error) aria-invalid="true" @endif
            {{ $attributes->except('id')->class('input') }}
        >

        @if ($clearable)
            <button type="button" @click="clear()" class="grid size-11 shrink-0 place-items-center rounded-lg border border-border text-text-muted hover:bg-surface-muted">
                <x-icon name="x" class="size-4" /><span class="sr-only">{{ __('ui.actions.clear') }}</span>
            </button>
        @endif
    </div>
</x-field>
