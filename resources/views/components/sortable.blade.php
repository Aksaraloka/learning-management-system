{{-- Anak langsung harus punya data-id; elemen pegangan beri atribut data-handle. Event `sorted` membawa {ids}. --}}
<ul x-data="sortableList()" {{ $attributes->class('space-y-2') }}>{{ $slot }}</ul>
