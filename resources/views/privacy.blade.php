<x-layouts.guest :title="__('ui.privacy.title')">
    <x-card :title="__('ui.privacy.title')">
        <div class="prose-content text-sm">
            <p>{{ institution_name() }} memproses data pribadi pengguna (nama, email, nomor induk, nilai, presensi, dan aktivitas belajar) hanya untuk keperluan pembelajaran, sesuai prinsip UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi.</p>
            <ul>
                <li>Data hanya dapat diakses oleh pengguna yang berwenang di institusi ini.</li>
                <li>Siswa/mahasiswa hanya melihat nilainya sendiri.</li>
                <li>Anda berhak meminta salinan, perbaikan, atau penghapusan data kepada admin institusi.</li>
                <li>Kata sandi disimpan dalam bentuk hash dan tidak dapat dibaca siapa pun.</li>
            </ul>
            <p class="text-text-muted">Draf awal. Institusi perlu melengkapi nama pengendali data, kontak, dan masa retensi sebelum dipakai resmi.</p>
        </div>
    </x-card>
</x-layouts.guest>
