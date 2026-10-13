{{--
    Ikon Heroicons (resources/icons). Default: outline 24 px. Atribut `solid` memakai varian solid 16 px yang lebih
    tegas untuk ikon sangat kecil (badge). Nama lama diterjemahkan lewat config/icons.php.
--}}
@props(['name', 'solid' => false])

@php
    static $cache = [];

    $resolved = config('icons.aliases.'.$name, $name);
    $folder = $solid ? 'solid' : 'outline';
    $path = resource_path("icons/{$folder}/{$resolved}.svg");

    if (! is_file($path)) {
        // Cepat gagal saat development/test agar ikon yang salah ketik langsung ketahuan.
        throw_if(app()->environment(['local', 'testing']), \InvalidArgumentException::class, "Ikon tidak ditemukan: {$name} ({$folder}/{$resolved})");

        $svg = '<svg viewBox="0 0 24 24"></svg>';
    } else {
        $svg = $cache["{$folder}/{$resolved}"] ??= preg_replace(['/<!--.*?-->/s', '/\s(class|width|height|aria-hidden|data-slot)="[^"]*"/'], '', file_get_contents($path));
    }

    $attrs = $attributes->merge(['class' => 'size-5 shrink-0', 'aria-hidden' => 'true']);
@endphp

{!! preg_replace('/<svg/', '<svg '.$attrs, trim($svg), 1) !!}
