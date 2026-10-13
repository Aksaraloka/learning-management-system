<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#c62828">
    <meta name="robots" content="noindex">
    <title>{{ __('offline.title') }} · {{ institution_name() }}</title>
    {{-- Halaman ini di-cache service worker dan harus berdiri sendiri: CSS inline, tanpa aset hasil build. --}}
    <style>
        :root { --bg: #fff; --surface: #fff; --text: #18181b; --muted: #52525b; --border: #e4e4e7; --primary: #c62828; --primary-fg: #fff; }
        @media (prefers-color-scheme: dark) { :root { --bg: #000; --surface: #0b0b0d; --text: #f4f4f5; --muted: #a1a1aa; --border: #2c2c31; --primary: #ef4444; --primary-fg: #000; } }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); line-height: 1.5; }
        main { max-width: 36rem; margin: 0 auto; padding: 2.5rem 1rem; }
        h1 { font-size: 1.375rem; margin: 0 0 .5rem; }
        h2 { font-size: 1rem; margin: 2rem 0 .5rem; }
        p { margin: 0 0 1rem; color: var(--muted); }
        .btn { display: inline-flex; min-height: 2.75rem; align-items: center; padding: 0 1.25rem; border: 0; border-radius: .5rem; background: var(--primary); color: var(--primary-fg); font: inherit; font-weight: 600; cursor: pointer; }
        ul { list-style: none; margin: 0; padding: 0; border: 1px solid var(--border); border-radius: .75rem; background: var(--surface); }
        li + li { border-top: 1px solid var(--border); }
        li a { display: block; padding: .75rem 1rem; color: var(--text); text-decoration: none; word-break: break-word; }
        li a:hover, li a:focus-visible { color: var(--primary); }
        li small { display: block; color: var(--muted); }
        [hidden] { display: none; }
    </style>
</head>
<body>
    <main>
        <h1>{{ __('offline.heading') }}</h1>
        <p>{{ __('offline.body') }}</p>
        <button type="button" class="btn" onclick="location.reload()">{{ __('offline.retry') }}</button>

        <section id="saved" hidden>
            <h2>{{ __('offline.saved') }}</h2>
            <p>{{ __('offline.saved_hint') }}</p>
            <ul id="saved-list"></ul>
        </section>
    </main>

    <script>
        // Berkas materi/lampiran yang pernah dibuka disimpan service worker di cache "materials"; tampilkan agar tetap bisa dibaca.
        (async function () {
            if (!('caches' in window)) return;

            try {
                var cache = await caches.open('aksaraloka-materials-v1');
                var requests = await cache.keys();
                if (!requests.length) return;

                var list = document.getElementById('saved-list');

                for (var i = 0; i < requests.length; i++) {
                    var response = await cache.match(requests[i]);
                    var disposition = response && response.headers.get('content-disposition') || '';
                    var match = disposition.match(/filename\*?=(?:UTF-8'')?"?([^";]+)"?/i);
                    var name = match ? decodeURIComponent(match[1]) : requests[i].url.split('/').slice(-2).join('/');

                    var li = document.createElement('li');
                    var a = document.createElement('a');
                    a.href = requests[i].url;
                    a.textContent = name;
                    var small = document.createElement('small');
                    small.textContent = response && response.headers.get('content-type') || '';
                    a.appendChild(small);
                    li.appendChild(a);
                    list.appendChild(li);
                }

                document.getElementById('saved').hidden = false;
            } catch (e) {}
        })();

        window.addEventListener('online', function () { location.reload(); });
    </script>
</body>
</html>
