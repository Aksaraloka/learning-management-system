<x-layouts.app title="Galeri Komponen">
    <div class="space-y-6">
        <x-card title="Tema">
            <x-theme-toggle variant="segmented" />
        </x-card>

        <x-card title="Warna (design token)">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach (['bg', 'surface', 'surface-muted', 'border', 'primary', 'success', 'warning', 'danger'] as $token)
                    <div class="text-sm">
                        <div class="h-12 rounded-lg border border-border" style="background: var(--color-{{ $token }})"></div>
                        <p class="mt-1 text-text-muted">{{ $token }}</p>
                    </div>
                @endforeach
            </div>
        </x-card>

        <x-card title="Tombol & badge">
            <div class="flex flex-wrap items-center gap-2">
                <x-button icon="plus">Primer</x-button>
                <x-button variant="secondary">Sekunder</x-button>
                <x-button variant="ghost">Ghost</x-button>
                <x-button variant="danger" icon="trash-2">Hapus</x-button>
                <x-button disabled>Nonaktif</x-button>
                <x-button size="sm">Kecil</x-button>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                <x-status-badge status="not_submitted" />
                <x-status-badge status="submitted" />
                <x-status-badge status="late" />
                <x-status-badge status="graded" />
                <x-badge variant="primary">Primer</x-badge>
                <x-badge>Netral</x-badge>
            </div>
        </x-card>

        <x-card title="Form">
            <form class="grid gap-4 sm:grid-cols-2" onsubmit="return false">
                <x-input name="name" label="Nama lengkap" placeholder="Mis. Budi Santoso" hint="Sesuai data akademik." />
                <x-input name="email" type="email" label="Email" value="salah-format" aria-invalid="true" />
                <x-select name="level" label="Jenjang" placeholder="Pilih jenjang" :options="['smp' => 'SMP', 'sma' => 'SMA', 'smk' => 'SMK']" />
                <x-input.select name="student" label="Siswa (cari)" :options="['1' => 'Andi Pratama', '2' => 'Bunga Lestari', '3' => 'Citra Dewi', '4' => 'Dimas Aditya']" />
                <x-input.select name="classes" label="Kelas (banyak)" multiple :options="['7a' => 'VII-A', '7b' => 'VII-B', '8a' => 'VIII-A']" />
                <x-input.date name="due" label="Tenggat" time />
                <x-textarea name="note" label="Catatan" class="sm:col-span-2" />
                <div class="sm:col-span-2">
                    <x-editor name="instructions" label="Instruksi tugas (Tiptap)" value="<p>Kerjakan soal berikut.</p>" />
                </div>
                <div class="sm:col-span-2">
                    <x-input.file name="attachment" label="Lampiran (maks. 50 MB)" :accept="['application/pdf', 'image/*']" />
                </div>
            </form>
        </x-card>

        <x-card title="Rumus (KaTeX)">
            <p x-data="mathContent" class="prose-content">Luas lingkaran: $L = \pi r^2$ dan $$\int_0^1 x^2\,dx = \tfrac{1}{3}$$</p>
        </x-card>

        <x-card title="Tabel responsif" :padding="false">
            <x-table>
                <thead><tr><th>Tugas</th><th>Tenggat</th><th>Status</th></tr></thead>
                <tbody>
                    <tr><td data-label="Tugas">Esai Sumpah Pemuda</td><td data-label="Tenggat">20 Okt 2026</td><td data-label="Status"><x-status-badge status="submitted" /></td></tr>
                    <tr><td data-label="Tugas">Latihan Aljabar</td><td data-label="Tenggat">18 Okt 2026</td><td data-label="Status"><x-status-badge status="late" /></td></tr>
                    <tr><td data-label="Tugas">Laporan Praktikum</td><td data-label="Tenggat">25 Okt 2026</td><td data-label="Status"><x-status-badge status="not_submitted" /></td></tr>
                </tbody>
            </x-table>
        </x-card>

        <x-card title="Tab, modal, dropdown, toast">
            <x-tabs :tabs="['a' => 'Materi', 'b' => 'Tugas', 'c' => 'Nilai']">
                <x-tab name="a"><p class="text-text-muted">Isi tab materi.</p></x-tab>
                <x-tab name="b"><p class="text-text-muted">Isi tab tugas.</p></x-tab>
                <x-tab name="c"><p class="text-text-muted">Isi tab nilai.</p></x-tab>
            </x-tabs>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <x-button variant="secondary" @click="$dispatch('open-modal', 'demo')">Buka modal</x-button>
                <x-button variant="secondary" @click="$dispatch('toast', { message: 'Tugas berhasil disimpan.', type: 'success' })">Tampilkan toast</x-button>
                <x-dropdown :label="__('ui.actions.more')" align="left">
                    <x-slot:trigger>{{ __('ui.actions.more') }} <x-icon name="chevron-down" class="size-4" /></x-slot:trigger>
                    <button type="button" role="menuitem" class="flex min-h-10 w-full items-center gap-2 rounded-md px-3 text-sm hover:bg-surface-muted"><x-icon name="pencil" class="size-4" /> {{ __('ui.actions.edit') }}</button>
                    <button type="button" role="menuitem" class="flex min-h-10 w-full items-center gap-2 rounded-md px-3 text-sm text-danger hover:bg-surface-muted"><x-icon name="trash-2" class="size-4" /> {{ __('ui.actions.delete') }}</button>
                </x-dropdown>
            </div>

            <x-modal name="demo" title="Hapus tugas?">
                <p class="text-text-muted">Tindakan ini tidak dapat dibatalkan.</p>
                <x-slot:footer>
                    <x-button variant="secondary" @click="$dispatch('close-modal', 'demo')">{{ __('ui.actions.cancel') }}</x-button>
                    <x-button variant="danger" @click="$dispatch('close-modal', 'demo')">{{ __('ui.actions.delete') }}</x-button>
                </x-slot:footer>
            </x-modal>
        </x-card>

        <div class="grid gap-6 lg:grid-cols-2">
            <x-card title="Empty state"><x-empty-state><x-button size="sm" icon="plus">Tambah tugas</x-button></x-empty-state></x-card>
            <x-card title="Skeleton"><x-skeleton :lines="4" /></x-card>
        </div>

        <x-card title="Urutkan (SortableJS)">
            <x-sortable>
                @foreach (['Pengantar', 'Bab 1: Bilangan', 'Bab 2: Aljabar'] as $i => $t)
                    <li data-id="{{ $i + 1 }}" class="flex items-center gap-3 rounded-lg border border-border bg-surface px-3 py-2">
                        <span data-handle class="cursor-grab text-text-muted"><x-icon name="grip-vertical" /></span>{{ $t }}
                    </li>
                @endforeach
            </x-sortable>
        </x-card>

        <div class="grid gap-6 lg:grid-cols-2">
            <x-card title="Grafik (Chart.js)">
                <x-chart type="bar" label="Distribusi nilai" :data="['labels' => ['<60', '60-70', '70-80', '80-90', '90+'], 'datasets' => [['label' => 'Siswa', 'data' => [2, 5, 12, 8, 3]]]]"
                         :options="['plugins' => ['legend' => ['display' => false]]]" />
            </x-card>
            <x-card title="Pratinjau PDF (PDF.js)">
                <x-pdf-viewer :url="asset('samples/sample.pdf')" />
            </x-card>
        </div>

        <x-card title="Istilah per mode">
            <p class="text-sm text-text-muted">Sekolah: {{ term('teacher', 'school') }}, {{ term('student', 'school') }}, {{ term('section', 'school') }} ·
                Kampus: {{ term('teacher', 'campus') }}, {{ term('student', 'campus') }}, {{ term('section', 'campus') }}</p>
        </x-card>
    </div>
</x-layouts.app>
