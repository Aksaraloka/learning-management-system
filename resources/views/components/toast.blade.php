{{-- Notifikasi singkat dan konfirmasi memakai SweetAlert2 (resources/js/lib/alert.js, dimuat lazy), jadi tidak ada wadah HTML di sini.
     Komponen ini dipertahankan agar layout tetap memanggil <x-toast />: ia hanya menyediakan teks tombol konfirmasi. --}}
<script>
    document.documentElement.dataset.confirmYes = @js(__('ui.actions.confirm_yes'));
    document.documentElement.dataset.confirmNo = @js(__('ui.actions.cancel'));
</script>
