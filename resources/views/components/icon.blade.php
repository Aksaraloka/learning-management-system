@props(['name'])

@php
    static $cache = [];

    $path = resource_path('icons/'.$name.'.svg');

    $svg = $cache[$name] ??= is_file($path)
        ? preg_replace(['/<!--.*?-->/s', '/\s(class|width|height)="[^"]*"/'], '', file_get_contents($path))
        : '<svg viewBox="0 0 24 24"></svg>';

    $attrs = $attributes->merge(['class' => 'size-5 shrink-0', 'aria-hidden' => 'true']);
@endphp

{!! preg_replace('/<svg/', '<svg '.$attrs, trim($svg), 1) !!}
