@props(['title' => null])

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#c62828">
<link rel="manifest" href="/manifest.webmanifest">
<link rel="icon" href="/icons/icon-192.png" type="image/png" sizes="192x192">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Aksaraloka">
<title>{{ $title ? $title.' · ' : '' }}{{ institution_name() }}</title>

{{-- Pasang class `dark` sebelum CSS dimuat agar tidak berkedip. Kunci sama dengan store tema (_x_theme).
     Jika perangkat belum punya pilihan, pakai tema yang tersimpan di akun (users.theme). --}}
<script>
    (function () {
        try {
            var raw = localStorage.getItem('_x_theme');
            var t = raw ? JSON.parse(raw) : null;
            var server = @js(auth()->user()?->theme);

            if (!t && server) {
                t = server;
                localStorage.setItem('_x_theme', JSON.stringify(t));
            }

            t = t || 'system';
            var d = t === 'dark' || (t !== 'light' && matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', d);
            document.documentElement.style.colorScheme = d ? 'dark' : 'light';
        } catch (e) {}
    })();
</script>

@vite(['resources/css/app.css', 'resources/js/app.js'])
@livewireStyles
