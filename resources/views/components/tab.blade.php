@props(['name'])

<div x-show="active === '{{ $name }}'" x-cloak role="tabpanel" id="panel-{{ $name }}" aria-labelledby="tab-{{ $name }}" tabindex="0">
    {{ $slot }}
</div>
