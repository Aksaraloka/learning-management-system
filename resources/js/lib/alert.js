/**
 * SweetAlert2 untuk konfirmasi dan notifikasi singkat (toast).
 *
 *  - Dimuat lazy (import dinamis) saat pertama dipakai agar halaman awal tetap ringan.
 *  - Warna mengikuti token tema (lihat resources/css/app.css, blok `.swal2-*`); tidak ada warna yang di-hardcode di sini.
 *  - Konfirmasi: semua elemen `wire:confirm="Pesan"` otomatis memakai SweetAlert2, tanpa mengubah markup.
 *  - Toast: `window.dispatchEvent(new CustomEvent('toast', { detail: { message, type } }))` atau dari Livewire
 *    `$this->dispatch('toast', message: '...', type: 'success|error|warning|info')`.
 */
let swalPromise = null;

export function loadSwal() {
    swalPromise ??= import('sweetalert2').then((module) => {
        const Swal = module.default;

        return Swal.mixin({
            buttonsStyling: false,
            reverseButtons: true,
            focusCancel: false,
            customClass: {
                popup: 'swal-aksara',
                confirmButton: 'swal-btn swal-btn-primary',
                cancelButton: 'swal-btn swal-btn-secondary',
                denyButton: 'swal-btn swal-btn-danger',
            },
        });
    });

    return swalPromise;
}

const ICONS = { success: 'success', error: 'error', warning: 'warning', info: 'info' };

export async function showToast({ message, type = 'info', timeout = 3500 } = {}) {
    if (!message) {
        return;
    }

    const Swal = await loadSwal();
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: ICONS[type] ?? 'info',
        title: message,
        showConfirmButton: false,
        showCloseButton: true,
        timer: timeout,
        timerProgressBar: !reduceMotion,
        showClass: reduceMotion ? { popup: '' } : undefined,
        hideClass: reduceMotion ? { popup: '' } : undefined,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        },
    });
}

/**
 * Dialog konfirmasi. Mengembalikan true bila pengguna menekan tombol konfirmasi.
 */
export async function confirmDialog({ message, confirmText = 'Ya, lanjutkan', cancelText = 'Batal', danger = false } = {}) {
    const Swal = await loadSwal();

    const result = await Swal.fire({
        icon: danger ? 'warning' : 'question',
        title: message,
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: cancelText,
        customClass: {
            popup: 'swal-aksara',
            confirmButton: danger ? 'swal-btn swal-btn-danger' : 'swal-btn swal-btn-primary',
            cancelButton: 'swal-btn swal-btn-secondary',
        },
    });

    return result.isConfirmed;
}

/** Pesan konfirmasi yang menyiratkan penghapusan memakai tombol merah. */
const looksDestructive = (message) => /hapus|buang|batalkan|keluar|kunci|hapus permanen|reset|atur ulang/i.test(message);

/**
 * Mencegat klik pada elemen `wire:confirm` sebelum Livewire memunculkan `window.confirm` bawaan. Setelah pengguna
 * menyetujui, klik diulang dengan `window.confirm` sementara disetel true agar Livewire melanjutkan aksinya.
 * Penangan berada di fase capture pada `document`, jadi berjalan lebih dulu daripada penangan Livewire.
 */
export function registerConfirm() {
    const labels = () => ({
        confirmText: document.documentElement.dataset.confirmYes || 'Ya, lanjutkan',
        cancelText: document.documentElement.dataset.confirmNo || 'Batal',
    });

    document.addEventListener(
        'click',
        (event) => {
            const el = event.target instanceof Element ? event.target.closest('[wire\\:confirm]') : null;

            if (!el || el.dataset.confirmBypass === '1') {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();

            const message = el.getAttribute('wire:confirm') ?? '';

            confirmDialog({ message, danger: looksDestructive(message), ...labels() }).then((confirmed) => {
                if (!confirmed) {
                    return;
                }

                const nativeConfirm = window.confirm;

                el.dataset.confirmBypass = '1';
                window.confirm = () => true;

                try {
                    el.click();
                } finally {
                    window.confirm = nativeConfirm;
                    delete el.dataset.confirmBypass;
                }
            });
        },
        true,
    );
}
