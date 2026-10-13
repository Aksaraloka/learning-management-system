# Aksaraloka

LMS web untuk **satu universitas** di Indonesia. Laravel 13, Livewire 4, Tailwind CSS v4, mobile-first.

> Status: **Fase 0 (fondasi), Fase 1 (auth, peran, pengguna, semester), Fase 2 (mata kuliah & materi), Fase 3 (tugas), Fase 4 (kuis), dan Fase 5 (nilai, presensi, pengumuman, notifikasi)** selesai.
> Aplikasi khusus **perguruan tinggi** dan melayani **satu universitas** (mode sekolah dan multi-tenant sudah dihapus; lihat
> [docs/decisions.md](docs/decisions.md)). Rencana lengkap ada di prompt awal proyek.

## Menjalankan di lokal

Prasyarat: PHP 8.3+, Composer, Node 20+, database (MySQL/PostgreSQL; SQLite untuk test).

```sh
composer install && npm install
cp .env.example .env && php artisan key:generate
# atur DB_* dan INSTITUTION_NAME di .env, lalu:
php artisan migrate --seed     # --seed = data demo
composer dev                   # server + queue + vite
```

Buka <http://localhost:8000>. Tidak butuh domain atau subdomain.

### Universitas

Nama universitas diatur lewat `.env`: `INSTITUTION_NAME="Universitas Demo Nusantara"`.

Istilah UI memakai kamus perguruan tinggi (`lang/id/terms.php`): Dosen, Mahasiswa, Mata Kuliah, Pertemuan, Dosen Wali,
Ketua Program Studi, NIM/NIDN, Semester, SKS.

### Produksi / tanpa data demo

```sh
php artisan migrate --force
php artisan aksaraloka:install --name="Admin Akademik" --email=admin@kampus.ac.id   # kata sandi acak ditampilkan sekali
```

## Akun demo

Semua kata sandi: `password`. Hasil `php artisan db:seed` (DemoSeeder).

| Peran | Login |
| --- | --- |
| Admin | `admin@aksaraloka.test` |
| Ketua program studi | `kaprodi@aksaraloka.test` |
| Dosen + dosen wali | `dosen1@aksaraloka.test` |
| Dosen | `dosen2@aksaraloka.test`, `dosen3@aksaraloka.test` |
| Mahasiswa (email) | `mahasiswa1@aksaraloka.test` … `mahasiswa5@aksaraloka.test` |
| Mahasiswa (NIM saja) | `2260006` … `2260030` |

Login menerima email **atau** NIM/NIDN. Data demo berisi 12 mata kuliah (mis. Algoritma dan Pemrograman, Basis Data, Struktur Data, Kecerdasan Buatan; tiap dosen mengampu 4 mata kuliah;
mahasiswa1–5 mengikuti semuanya) lengkap dengan pertemuan, materi (teks, PDF, tautan, video), tugas, dan kuis (*Kuis Harian* yang masih dibuka dan *Kuis Evaluasi* yang sudah ditutup dengan beberapa percobaan).

## Mata kuliah & materi

- **Mata kuliah** dibuat dosen atau admin dan ditautkan ke semester; **SKS** (1–24) wajib. Setiap mata kuliah punya **kode gabung** 6 karakter; mahasiswa bergabung lewat menu Mata Kuliah, atau dosen menambahkan banyak peserta sekaligus di halaman Anggota (tempel NIM/NIDN/email).
- **Pertemuan 1–16** berisi materi: teks (editor Tiptap + rumus KaTeX), berkas (PDF dan gambar dapat dipratinjau), tautan, dan video (YouTube/Vimeo dimuat setelah tombol putar ditekan). Pertemuan dan materi dapat diurutkan dengan drag & drop.
- **Hak akses**: admin dan dosen pengampu mengelola; mahasiswa membaca mata kuliah yang diikutinya; ketua program studi melihat semua mata kuliah (hanya baca). Berkas disimpan privat dan hanya disajikan kepada anggota mata kuliah.

## Tugas

- **Dosen/admin** membuat tugas di pertemuan: instruksi (editor + rumus), lampiran (maks. 10 berkas), tenggat, nilai maksimal, kategori nilai, dan opsi menerima pengumpulan terlambat. Tugas dapat **disalin ke kelas paralel** (semester yang sama) beserta lampirannya.
- **Mahasiswa** mengumpulkan teks dan/atau satu berkas, dan boleh mengganti kiriman sebelum tenggat. Setelah dinilai kiriman terkunci; bila **dikembalikan untuk revisi** mahasiswa boleh mengumpulkan ulang (walau lewat tenggat). Halaman **Tugas** menampilkan tenggat terdekat lebih dulu dan bisa difilter menurut status (Belum dikumpulkan, Dikumpulkan, Terlambat, Dinilai, Perlu revisi).
- **Layar penilaian**: daftar mahasiswa di kiri (yang perlu dinilai di atas), pratinjau kiriman (teks, PDF, gambar) + nilai + umpan balik di kanan, tombol *Simpan & berikutnya* dan *Kembalikan untuk revisi*. Di HP daftar dan detail bergantian.
- **Komentar** per tugas: publik (seluruh mata kuliah) atau pribadi mahasiswa↔dosen (dosen membalas dari layar penilaian).
- Penilaian **terkunci** saat admin mengunci semester (menu Semester); ketua program studi hanya dapat melihat.

## Kuis

- **Dosen/admin** membuat kuis di pertemuan (menu ⌄ pada pertemuan → *Tambah kuis*): jadwal buka/tutup, durasi, jumlah percobaan, acak urutan soal/pilihan, kategori nilai, dan kapan nilai ditampilkan. Soal ada 4 jenis: **pilihan ganda, benar/salah, isian singkat** (beberapa jawaban diterima, huruf besar/kecil diabaikan) dan **esai** (dinilai manual). Soal bisa bergambar, memakai rumus KaTeX, dan diurutkan dengan drag & drop. Kuis baru berstatus draf sampai diterbitkan.
- **Mahasiswa** mengerjakan semua soal dalam satu halaman: di HP satu soal per layar dengan nomor soal sebagai navigasi, penghitung waktu, dan tanda *ragu-ragu*. **Jawaban tersimpan otomatis** (dan dicadangkan di perangkat bila koneksi putus, dikirim saat tersambung). Waktu dihitung server: menutup halaman tidak menghentikan waktu, dan percobaan yang waktunya habis dikumpulkan otomatis (scheduler `quizzes:finalize-expired` tiap menit; di production jalankan `php artisan schedule:work` atau cron `schedule:run`).
- Soal objektif **dinilai otomatis**; kuis dengan esai berstatus *Menunggu penilaian* sampai dosen menilai esai di halaman tinjauan. Kunci jawaban baru terlihat mahasiswa setelah kuis ditutup atau percobaan habis, dan hanya bila nilai sudah boleh ditampilkan.
- **Hasil** (dosen, admin, ketua program studi): ringkasan (rata-rata, tertinggi, terendah), daftar percobaan yang bisa difilter, dan tinjauan jawaban per mahasiswa. Penilaian esai **terkunci** saat semester dikunci. Rencana uji beban: [docs/load-testing.md](docs/load-testing.md).

## Buku nilai, presensi, pengumuman, notifikasi

- **Buku nilai** (`Mata Kuliah → Buku nilai`): nilai tugas dan kuis digabung per kategori berbobot (bawaan Tugas 40 / Kuis 30 / Ujian 30; dosen dapat mengubah, menambah, atau menghapus kategori, total harus 100%). Yang tidak dikerjakan setelah tenggat dihitung 0, kuis memakai percobaan terbaik, dan yang menunggu penilaian dosen belum dihitung. Dosen dan ketua program studi melihat semua mahasiswa dan dapat **mengekspor Excel**; mahasiswa hanya melihat rincian nilainya sendiri.
- **Presensi**: dosen membuat sesi per pertemuan dan mengisi Hadir/Izin/Sakit/Alpa (tombol *Semua hadir* untuk mempercepat); mahasiswa melihat rekap kehadirannya. Persentase kehadiran tampil di buku nilai.
- **Pengumuman**: per mata kuliah (dosen) atau seluruh universitas (admin), bisa disematkan; anggota mendapat notifikasi.
- **Notifikasi** (menu Notifikasi, dengan lencana belum dibaca): tugas baru, kuis dibuka, pengumuman, nilai keluar, dan **pengingat H-1** tenggat tugas/kuis bagi yang belum mengerjakan. Pengiriman lewat antrean sehingga **worker harus berjalan** (`composer dev`, atau `php artisan queue:work`); pengingat dikirim scheduler `reminders:due` tiap jam (`php artisan schedule:work` di dev, cron `schedule:run` di production). Untuk mengisi data demo tanpa worker: `QUEUE_CONNECTION=sync php artisan migrate:fresh --seed`.

### Batas unggah berkas

Berlaku untuk materi, tugas, dan gambar soal kuis (gambar soal maks. 2 MB). Maksimal 50 MB per berkas (`config/courses.php`), tetapi tidak pernah melebihi batas PHP. Halaman unggah menampilkan batas yang sebenarnya berlaku.
Bawaan PHP hanya 2 MB, jadi untuk 50 MB ubah `php.ini` (Herd: `%USERPROFILE%\.config\herd\bin\php84\php.ini`) lalu restart server:

```ini
upload_max_filesize = 64M
post_max_size = 64M
```

Berkas disimpan di disk `COURSE_DISK` (default `local`, folder `storage/app/private/courses`). Ganti ke `s3` untuk S3/Cloudflare R2.

## Impor pengguna

Admin: **Pengguna → Impor pengguna**. Unduh templat CSV, isi kolom `name, identity_number, email, role, password`
(`role` boleh beberapa, dipisah `|`, mis. `teacher|homeroom`; bisa juga `dosen`, `mahasiswa`, `kaprodi`).
Kata sandi kosong → kata sandi awal = NIM/NIDN. Peran `admin` tidak dapat diimpor. Pemrosesan lewat antrean (queue `imports`);
jalankan worker dengan `php artisan queue:work --queue=imports,default` (atau `composer dev`).

## Perintah penting

```sh
php artisan test                                   # Pest
./vendor/bin/pint                                  # format
./vendor/bin/phpstan analyse --memory-limit=1G     # Larastan level 6
npm run build                                      # build aset
```

Galeri komponen UI: `/ui` (hanya `APP_ENV=local`).

## Dashboard, audit, dan PWA

- **Dashboard per peran** (halaman Beranda): *mahasiswa* melihat tenggat terdekat, nilai terbaru, dan nilai akhir/kehadiran per mata kuliah (grafik); *dosen* melihat yang perlu dinilai, tenggat 7 hari, dan statistik mata kuliahnya (rata-rata, kehadiran, pengumpulan); *ketua program studi* melihat semua mata kuliah, rata-rata, dan sebaran nilai A–E; *admin* melihat jumlah pengguna/mata kuliah/semester, grafik, dan aktivitas audit terbaru. Statistik mata kuliah di-cache 5 menit.
- **Jejak audit** (`/admin/audit`, admin dan ketua program studi): mencatat siapa mengubah nilai tugas (nilai lama → baru), nilai esai, hitung ulang kuis, bobot nilai, presensi, penguncian/penghapusan semester, reset kata sandi, impor pengguna, dan penghapusan mata kuliah. Catatan hanya bisa ditambah; kata sandi dan isi jawaban tidak pernah dicatat.
- **PWA**: aplikasi dapat dipasang di layar utama (Chrome/Android: *Instal aplikasi*). Service worker (`public/sw.js`, aktif di build production) menyimpan aset build, menampilkan halaman *Offline* saat tak ada koneksi, dan menyimpan **berkas materi/lampiran** yang dibuka lewat tombol *Simpan untuk offline* agar tetap bisa dibaca. Halaman (HTML) dan pembaruan Livewire tidak pernah di-cache; salinan materi dihapus saat keluar (logout) atau bila akses dicabut. Jawaban kuis yang tertunda tetap tersimpan di perangkat dan dikirim saat tersambung (lihat bagian Kuis). Ikon aplikasi ada di `public/icons` (sumber: `icon.svg`).
- **Halaman error** (403, 404, 419, 429, 500, 503) berbahasa Indonesia.

### Ukuran halaman

Terukur di Chrome headless (HP 390 px, tanpa cache), ukuran transfer **setelah gzip**: halaman biasa ±120 KB (JS inti Livewire+Alpine 98 KB, CSS 11 KB, HTML ±10 KB); dashboard dengan grafik ±215 KB (Chart.js 67 KB dan GSAP 26 KB dimuat lazy); halaman dengan editor/pratinjau PDF memuat pustaka tambahan sesuai kebutuhan saja (PDF.js 125 KB hanya saat membuka PDF). Semua di bawah target 500 KB. Server dev (`artisan serve`) tidak mengompres, jadi aktifkan gzip/brotli di web server.

## Catatan produksi

Aplikasi ini monolit Laravel; berikut konfigurasi yang disarankan untuk universitas sungguhan.

**Server**: Linux, PHP 8.3+ (OPcache aktif, `opcache.validate_timestamps=0`), PHP-FPM di belakang Nginx/Caddy dengan **HTTPS wajib** (service worker, cookie `Secure`) dan gzip/brotli. Setelah deploy: `composer install --no-dev -o`, `npm ci && npm run build`, `php artisan migrate --force`, `php artisan config:cache route:cache view:cache event:cache`.

**`.env` production** (selain kredensial):

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://lms.kampus.ac.id
INSTITUTION_NAME="Universitas ..."
DB_CONNECTION=pgsql          # PostgreSQL; pakai PgBouncer (mode transaction) bila koneksi menjadi batas
SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_SECURE_COOKIE=true
COURSE_DISK=s3               # S3/Cloudflare R2 untuk berkas materi, tugas, dan gambar soal (privat)
MAIL_MAILER=smtp             # untuk reset kata sandi
```

**Antrean dan scheduler** (wajib, jika tidak notifikasi dan penutupan kuis otomatis tidak berjalan):
- Worker: **Horizon** (`php artisan horizon`, di bawah Supervisor/systemd; antrean `default` dan `imports`; butuh `ext-pcntl`/`ext-posix`, jadi hanya di Linux). Alternatif sederhana: `php artisan queue:work --queue=default,imports`.
- Scheduler: cron `* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1` menjalankan `quizzes:finalize-expired` (tiap menit) dan `reminders:due` (tiap jam).
- `php artisan horizon:terminate` setelah setiap deploy agar worker memuat kode baru.

**Keamanan**: berkas materi/tugas/kiriman/gambar soal privat dan hanya disajikan controller yang memeriksa hak akses; rich text disanitasi (Purifier); kunci jawaban kuis tidak dikirim ke peserta; ekspor Excel dinetralkan dari injeksi rumus; unggahan divalidasi isi dan ekstensi. Batasi `upload_max_filesize`/`post_max_size` sesuai `config/courses.php`.

**Cadangan**: jadwalkan dump database harian dan salinan bucket berkas; uji pemulihan. Jejak audit ada di database (`audit_logs`) sehingga ikut tercadangkan.

**Kapasitas kuis**: rencana dan kriteria uji beban 1.000 mahasiswa serentak ada di [docs/load-testing.md](docs/load-testing.md) (belum diukur; butuh lingkungan Linux + PostgreSQL + Redis).

**Pemantauan**: halaman `/horizon` (admin) untuk antrean; log aplikasi di `storage/logs`; pasang pelacak galat (mis. Sentry/Flare) bila diperlukan.
