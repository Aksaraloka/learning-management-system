@props(['type' => 'bar', 'data', 'options' => [], 'label' => null, 'height' => 'h-64'])

<div x-data="themedChart({ type: @js($type), data: @js($data), options: @js((object) $options) })" {{ $attributes->class('relative '.$height) }}>
    <canvas x-ref="canvas" role="img" @if ($label) aria-label="{{ $label }}" @endif></canvas>
</div>
