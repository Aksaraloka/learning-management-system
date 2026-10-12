{{--
    Anak langsung harus punya data-id; elemen pegangan beri atribut sesuai `handle`.
    Event `sorted` membawa {ids}. Daftar bersarang harus memakai pegangan yang berbeda.
--}}
@props(['handle' => '[data-handle]'])

<ul x-data="sortableList({ handle: @js($handle) })" {{ $attributes->class('space-y-2') }}>{{ $slot }}</ul>
