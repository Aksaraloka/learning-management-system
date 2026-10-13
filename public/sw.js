/*
 * Service worker Aksaraloka.
 *
 *  - Aset hasil build (/build/*, /icons/*): cache-first. Nama berkas ber-hash sehingga aman disimpan lama.
 *  - Halaman (navigasi): network-first; bila offline tampil /offline. HTML TIDAK pernah di-cache karena berisi data pribadi.
 *  - Berkas materi dan lampiran tugas: network-first, salinan terakhir disimpan agar bisa dibaca offline. Salinan dihapus bila
 *    server menjawab 401/403/404 (akses dicabut) dan semua salinan dihapus saat pengguna keluar (logout).
 *  - Tidak disentuh: /livewire (pembaruan komponen, autosave kuis), semua metode selain GET, unggahan, dan halaman kuis.
 */
const VERSION = 'v1';
const ASSETS = `aksaraloka-assets-${VERSION}`;
const MATERIALS = 'aksaraloka-materials-v1'; // nama sama dengan yang dibaca halaman /offline dan tombol logout
const PAGES = `aksaraloka-pages-${VERSION}`;
const OFFLINE_URL = '/offline';
const MAX_MATERIALS = 40;

const PRIVATE_FILE = /^\/(materi\/\d+\/berkas|tugas\/\d+\/lampiran\/\d+)$/;
const STATIC_ASSET = /^\/(build\/|icons\/|favicon\.ico)/;

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(PAGES)
            .then((cache) => cache.add(new Request(OFFLINE_URL, { cache: 'reload' })))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys
                .filter((key) => key.startsWith('aksaraloka-') && ![ASSETS, MATERIALS, PAGES].includes(key))
                .map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('message', (event) => {
    // Dikirim saat logout: buang semua salinan data pribadi.
    if (event.data === 'clear-private') {
        event.waitUntil(caches.delete(MATERIALS));
    }
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin || url.pathname.startsWith('/livewire') || url.pathname.startsWith('/logout')) {
        return;
    }

    if (STATIC_ASSET.test(url.pathname)) {
        event.respondWith(cacheFirst(request));

        return;
    }

    if (PRIVATE_FILE.test(url.pathname) && !request.headers.has('range')) {
        event.respondWith(networkFirstFile(request, url));

        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(navigate(request));
    }
});

async function cacheFirst(request) {
    const cache = await caches.open(ASSETS);
    const hit = await cache.match(request);

    if (hit) {
        return hit;
    }

    const response = await fetch(request);

    if (response.ok) {
        cache.put(request, response.clone());
    }

    return response;
}

async function navigate(request) {
    try {
        return await fetch(request);
    } catch (error) {
        const cache = await caches.open(PAGES);

        return (await cache.match(OFFLINE_URL)) ?? new Response('Offline', { status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
    }
}

async function networkFirstFile(request, url) {
    // Kunci cache tanpa query (?download=1) agar pratinjau dan unduhan memakai satu salinan.
    const key = new Request(url.origin + url.pathname);
    const cache = await caches.open(MATERIALS);

    try {
        const response = await fetch(request);

        if (response.ok) {
            await cache.put(key, response.clone());
            await trim(cache);
        } else if ([401, 403, 404].includes(response.status)) {
            await cache.delete(key);
        }

        return response;
    } catch (error) {
        const hit = await cache.match(key);

        if (hit) {
            return hit;
        }

        throw error;
    }
}

async function trim(cache) {
    const keys = await cache.keys();

    // Batasi jumlah salinan; yang tertua (urutan penyimpanan) dibuang lebih dulu.
    for (let i = 0; i < keys.length - MAX_MATERIALS; i++) {
        await cache.delete(keys[i]);
    }
}
