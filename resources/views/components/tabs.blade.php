{{-- <x-tabs :tabs="['a' => 'Label A', 'b' => 'Label B']"> <x-tab name="a">…</x-tab> … </x-tabs> --}}
@props(['tabs', 'default' => null])

@php($keys = array_keys($tabs))

<div x-data="{ active: @js($default ?? $keys[0]), keys: @js($keys) }" {{ $attributes }}>
    <div role="tablist" class="flex gap-1 overflow-x-auto border-b border-border"
         @keydown.right.prevent="active = keys[(keys.indexOf(active) + 1) % keys.length]; $nextTick(() => $refs['tab-' + active].focus())"
         @keydown.left.prevent="active = keys[(keys.indexOf(active) - 1 + keys.length) % keys.length]; $nextTick(() => $refs['tab-' + active].focus())">
        @foreach ($tabs as $key => $label)
            <button type="button" role="tab" x-ref="tab-{{ $key }}" id="tab-{{ $key }}"
                    aria-controls="panel-{{ $key }}"
                    :aria-selected="active === '{{ $key }}'"
                    :tabindex="active === '{{ $key }}' ? 0 : -1"
                    @click="active = '{{ $key }}'"
                    :class="active === '{{ $key }}' ? 'border-primary text-primary' : 'border-transparent text-text-muted hover:text-text'"
                    class="-mb-px min-h-11 whitespace-nowrap border-b-2 px-4 text-sm font-medium">{{ $label }}</button>
        @endforeach
    </div>

    <div class="pt-4">{{ $slot }}</div>
</div>
