@props(['type' => 'bar', 'data', 'options' => [], 'label' => null])

<div x-data="themedChart({ type: @js($type), data: @js($data), options: @js((object) $options) })" {{ $attributes->class('relative h-64') }}>
    <canvas x-ref="canvas" role="img" @if ($label) aria-label="{{ $label }}" @endif></canvas>
</div>
