@props(['name' => null, 'label' => null, 'hint' => null, 'value' => ''])

@php
    $id = $attributes->get('id', $name ?? 'field-'.uniqid());
    $error = $name ? $errors->first($name) : null;
    $tools = [
        'bold' => ['icon' => 'bold', 'label' => __('ui.editor.bold')],
        'italic' => ['icon' => 'italic', 'label' => __('ui.editor.italic')],
        'h2' => ['icon' => 'heading-2', 'label' => __('ui.editor.heading')],
        'bullet' => ['icon' => 'list', 'label' => __('ui.editor.bullet')],
        'ordered' => ['icon' => 'list-ordered', 'label' => __('ui.editor.ordered')],
    ];
@endphp

<x-field :id="$id" :label="$label" :hint="$hint" :error="$error">
    <div wire:ignore x-data="richEditor({ label: @js($label ?? __('ui.editor.bold')) })"
         class="rounded-lg border border-border bg-surface focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-primary">
        <div class="flex gap-1 border-b border-border p-1" role="toolbar" aria-label="{{ $label }}">
            @foreach ($tools as $command => $tool)
                <button type="button" @click="run('{{ $command }}')"
                        :aria-pressed="active.{{ $command }} ? 'true' : 'false'"
                        :class="active.{{ $command }} ? 'bg-surface-muted text-primary' : 'text-text-muted'"
                        class="grid size-9 place-items-center rounded-md hover:bg-surface-muted" title="{{ $tool['label'] }}">
                    <x-icon :name="$tool['icon']" class="size-4" />
                    <span class="sr-only">{{ $tool['label'] }}</span>
                </button>
            @endforeach
        </div>
        <div x-ref="surface"></div>
        <textarea x-ref="field" id="{{ $id }}" hidden @if ($name) name="{{ $name }}" @endif {{ $attributes->except('id') }}>{{ $value }}</textarea>
    </div>
</x-field>
