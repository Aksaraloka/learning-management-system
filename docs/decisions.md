# Catatan Keputusan

Asumsi yang diambil saat PRD tidak mencakup detailnya.

## Fase 0

1. **Laravel 13 + Livewire 4**: skeleton yang sudah ada memakai Laravel 13; Livewire versi stabil terbaru (4.x). Alpine dibawa Livewire; plugin (`persist`, `focus`, `collapse`) didaftarkan lewat `livewire.esm`, dan `inject_assets` dimatikan agar satu bundle Vite.
2. **Breeze ditunda ke Fase 1**: stack Livewire Breeze menimpa layout/route/CSS. Dipasang di Fase 1 saat auth dibuat, dengan layout Aksaraloka sebagai dasar.
3. **Ikon Lucide**: paket `blade-lucide-icons` tidak kompatibel dengan Laravel 13. Dipakai `lucide-static`; SVG yang dibutuhkan disalin ke `resources/icons` dan dirender komponen `<x-icon>` (tanpa JS, tanpa request tambahan).
4. **Font**: Plus Jakarta Sans (self-host via `@fontsource-variable`).
5. **Horizon & Larastan di Windows**: membutuhkan `ext-pcntl`/`ext-posix` yang tidak ada di Windows; dipasang dengan `--ignore-platform-req`. Horizon hanya berjalan di Linux/WSL/production.
6. **Redis belum dipakai di dev**: `.env` lokal memakai SQLite + database queue/cache. Target production: PostgreSQL + Redis (dicatat di README Fase 6).
7. **Paket bahasa**: `laravel-lang/lang` tidak kompatibel; file `lang/id` (auth, validation, passwords, pagination) ditulis sendiri dan akan dilengkapi seiring kebutuhan.
8. **Zona waktu** `Asia/Jakarta`, locale dan fallback `id`.
9. **Tema**: pilihan disimpan di Alpine persist (`_x_theme`); event `theme-changed` dikirim untuk sinkron ke `users.theme` (diimplementasi Fase 1 saat tabel users diperluas).
10. **Warna status** memakai token `success/warning/danger/primary` dengan latar transparan 15%; nilai token sudah dipilih agar kontras ≥ 4.5:1.
11. **PWA**: `vite-plugin-pwa` terpasang tetapi belum dikonfigurasi (Fase 6).
12. **Contoh PDF** `public/samples/sample.pdf` hanya untuk galeri `/ui`.
13. **Plugin Alpine**: Livewire 4 sudah membundel `persist`, `focus`, dan `collapse`; mendaftarkannya lagi dari npm menyebabkan error `Cannot redefine property: $persist` sehingga paket `@alpinejs/*` tidak dipasang.

## Fase 1

14. **Breeze tidak dijalankan**: installer-nya menimpa layout/route Fase 0 dan mengasumsikan satu pengguna global. Auth (login, lupa/reset kata sandi, profil, logout) ditulis sebagai komponen Livewire sendiri dengan pola yang sama.
15. **Satu set rute untuk domain utama dan tenant**: konteks ditentukan middleware `ResolveTenant`, bukan `Route::domain`. Host tak dikenal (localhost, IP) diperlakukan sebagai domain utama agar mudah dipakai saat development.
16. **Email unik global, NIS/NIM unik per tenant**: tabel `password_reset_tokens` memakai email sebagai kunci, jadi email tidak boleh sama lintas tenant. Email boleh kosong (siswa login dengan NIS).
17. **Peran platform = team 0**: kolom `tenant_id` pada spatie `model_has_roles` ikut primary key sehingga tidak boleh NULL. Role `super_admin` disimpan dengan `tenant_id = 0`; akun super admin punya `users.tenant_id = NULL`.
18. **Hanya roles, tanpa permissions**: akses diatur Policy yang memeriksa peran; tabel permissions belum dipakai.
19. **Lapis isolasi**: (a) `TenantScope` pada semua model bertenant, termasuk provider auth sehingga sesi tenant lain tidak memuat pengguna; (b) middleware `tenant.member` menghentikan sesi bila `tenant_id` pengguna tidak cocok dengan host; (c) cookie sesi host-only (tidak lintas subdomain); (d) login di domain utama hanya untuk `tenant_id IS NULL`.
20. **Impor pengguna**: kata sandi awal = NIS/NIM/NIP bila kolom kosong (kebiasaan sekolah; admin dapat mereset). Peran `admin`/`super_admin`/`parent` tidak bisa diimpor (cegah eskalasi hak). Duplikat NIS/email ditolak, bukan di-update. Pratinjau memeriksa 200 baris pertama; seluruh baris diperiksa saat job. Impor kelas ditunda ke Fase 2 karena tabel `courses` belum ada. Hash per baris membuat ribuan baris lambat, tetapi berjalan di queue `imports`.
21. **Reset kata sandi oleh admin**: kata sandi acak 10 karakter ditampilkan sekali di layar admin (belum dikirim lewat email).
22. **Tahun ajaran terkunci**: tidak bisa diubah/dihapus sampai dibuka kuncinya oleh admin. Penguncian nilai oleh semester ditegakkan di Fase 5.
23. **Pesan lupa kata sandi selalu sama** (anti-enumerasi). Rate limit login: 5 percobaan gagal per kombinasi identitas+IP.
24. **Kebijakan privasi** berupa draf statis di `/kebijakan-privasi`; ekspor/hapus data pengguna oleh admin dikerjakan di fase berikutnya.
25. **Seeder demo Fase 1** hanya berisi pengguna dan tahun ajaran; kelas, materi, tugas, kuis ditambahkan seeder fase terkait.
26. **Tema tersinkron ke akun** lewat `PUT /theme`; perangkat baru tanpa pilihan lokal memakai `users.theme`.
27. **Domain root lokal = `localhost`**: `*.localhost` di-resolve browser tanpa DNS/hosts, sehingga tenant bisa dicoba tanpa membeli domain. Test dipaku ke `aksaraloka.test` lewat `phpunit.xml`.

## Perubahan metode tenant: subdomain -> path

28. **Tenant berbasis path, bukan subdomain** (menggantikan #15 dan #27; catatan #19(c) tidak berlaku). Alasan: pengguna tidak memiliki domain/DNS untuk subdomain. Tenant = segmen pertama URL (`/smpn1bandung/login`). Tabel `tenants.subdomain` diganti nama menjadi `slug` (migration baru; migration lama tidak diubah).
29. **Domain utama = slug `platform`**: parameter opsional di tengah URI (`{tenant?}/login`) tidak didukung Laravel, jadi satu set rute dengan prefix `{tenant}` wajib dipakai dan konteks platform memakai slug khusus `platform` (`/platform/login`, `/platform/institusi`). `/` hanya redirect ke `/platform`.
30. **URL dibangun otomatis**: `Tenancy::bind()` memasang `URL::defaults(['tenant' => slug])`, `forget()` memasang `platform`, sehingga `route('login')` tidak perlu diubah di kode/view.
31. **Update Livewire membawa tenant**: rute kustom `/livewire-xxx/update/{tenant?}` didaftarkan di `AppServiceProvider::register()` (mendahului rute bawaan Livewire, yang otomatis 404 bila ada rute kustom). Endpoint upload file tidak butuh tenant (hanya menyimpan file sementara bertanda tangan).
32. **Satu sesi per browser per host**: cookie sesi sama untuk semua slug. `TenantScope` pada provider auth + `tenant.member` membuat sesi tenant lain tidak berlaku di tenant ini, sehingga berpindah tenant berarti masuk ulang. Lapis isolasi lain tetap berlaku.
33. **Slug**: 3-32 karakter `[a-z0-9-]`, tidak boleh diawali/diakhiri strip, tidak boleh dalam `reserved_slugs` atau diawali `livewire-`.

## Perubahan: satu institusi (multi-tenant dihapus)

34. **Satu institusi per instalasi** (menggantikan #14-#19 dan #28-#33 yang terkait tenant). Alasan: kebutuhan produk berubah; tanpa subdomain/domain. Tidak ada `tenants`, `tenant_id`, `TenantScope`, middleware tenant, panel super admin, atau prefix URL. URL kembali sederhana (`/login`, `/admin/pengguna`).
35. **Nama & jenjang lewat `.env`** (`INSTITUTION_NAME`, `INSTITUTION_LEVEL`) dibaca `config/institution.php`, bukan tabel. Pengaturan lewat UI dapat ditambahkan nanti bila perlu.
36. **Peran**: `super_admin` dihapus. Spatie tanpa teams (`teams = false`). Peran dibuat oleh migration `remove_multi_tenancy` dan oleh action `EnsureRoles`; admin pertama dibuat dengan `php artisan aksaraloka:install`.
37. **Migration `2026_10_14_000001_remove_multi_tenancy`** (migration lama tidak diubah): membuang kolom `tenant_id`, tabel `tenants`, akun super admin, dan membangun ulang tabel peran spatie tanpa teams (peran & penugasan dipertahankan, peran duplikat per tenant digabung). Menolak berjalan bila ada lebih dari satu tenant karena NIS/email bisa bentrok; pada kasus itu pilih satu institusi atau pakai database baru. Jalur database baru terverifikasi di MySQL; jalur konversi dari database lama dengan satu tenant berperan belum diuji di MySQL.
38. **Isolasi data lintas tenant tidak relevan lagi**: test isolasi dihapus. Hak akses tetap diuji (admin, kepala, guru, siswa).
39. **Akun demo** memakai domain `@aksaraloka.test` (satu institusi).
