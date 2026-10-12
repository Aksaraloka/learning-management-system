/**
 * Animasi berbasis GSAP (inti saja, tanpa plugin). Prinsip untuk pengguna HP Android berkoneksi lambat:
 *  - GSAP dimuat lazy (chunk terpisah) hanya saat ada elemen yang beranimasi;
 *  - konten TIDAK PERNAH tertahan: bila GSAP belum siap dalam FALLBACK_MS, elemen langsung ditampilkan tanpa animasi;
 *  - mati total bila pengguna memilih `prefers-reduced-motion: reduce`;
 *  - hanya `opacity` dan `transform` (dirender GPU) dan dibersihkan setelah selesai;
 *  - jumlah elemen yang dianimasikan dibatasi (MAX_ITEMS).
 */
const FALLBACK_MS = 600;
const MAX_ITEMS = 12;

let gsapPromise = null;

export const prefersReducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

export function loadGsap() {
    gsapPromise ??= import('gsap').then((module) => module.gsap ?? module.default);

    return gsapPromise;
}

/**
 * Muncul bertahap (fade + naik sedikit) untuk anak-anak elemen.
 * Pakai: <ul x-reveal> atau <ul x-reveal.self> (animasikan elemen itu sendiri).
 */
function reveal(el, { modifiers }) {
    if (prefersReducedMotion() || el.dataset.revealed) {
        return;
    }

    el.dataset.revealed = '1';

    const targets = (modifiers.includes('self') ? [el] : [...el.children]).slice(0, MAX_ITEMS);

    if (targets.length === 0) {
        return;
    }

    targets.forEach((target) => (target.style.opacity = '0'));

    let timedOut = false;

    const showNow = () => targets.forEach((target) => (target.style.opacity = ''));
    const timer = setTimeout(() => {
        timedOut = true;
        showNow();
    }, FALLBACK_MS);

    loadGsap()
        .then((gsap) => {
            clearTimeout(timer);

            if (timedOut) {
                return;
            }

            gsap.fromTo(
                targets,
                { opacity: 0, y: 12 },
                { opacity: 1, y: 0, duration: 0.35, ease: 'power2.out', stagger: 0.05, clearProps: 'opacity,transform' },
            );
        })
        .catch(() => {
            clearTimeout(timer);
            showNow();
        });
}

/**
 * Angka naik dari 0 ke nilainya. Pakai: <p x-countup>42</p> (angka sudah ada di HTML, jadi tetap benar tanpa JS).
 */
function countup(el) {
    const end = Number.parseInt(el.textContent.replace(/\D/g, ''), 10);

    if (prefersReducedMotion() || Number.isNaN(end) || end < 2) {
        return;
    }

    loadGsap()
        .then((gsap) => {
            const counter = { value: 0 };

            gsap.to(counter, {
                value: end,
                duration: Math.min(1, 0.4 + end / 200),
                ease: 'power1.out',
                onUpdate: () => (el.textContent = Math.round(counter.value).toLocaleString('id-ID')),
                onComplete: () => (el.textContent = end.toLocaleString('id-ID')),
            });
        })
        .catch(() => {});
}

/**
 * Denyut singkat pada elemen yang baru dipindahkan (setelah drag & drop) sebagai umpan balik.
 */
export function pulse(el) {
    if (prefersReducedMotion()) {
        return;
    }

    loadGsap()
        .then((gsap) => gsap.fromTo(el, { scale: 1.02 }, { scale: 1, duration: 0.35, ease: 'back.out(3)', clearProps: 'transform' }))
        .catch(() => {});
}

export function registerMotion(Alpine) {
    Alpine.directive('reveal', (el, binding) => reveal(el, binding));
    Alpine.directive('countup', (el) => countup(el));
}
