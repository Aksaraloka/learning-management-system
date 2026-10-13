# Aksaraloka

LMS web untuk **satu universitas** di Indonesia. Laravel 13, Livewire 4, Tailwind CSS v4, mobile-first.

> Status: **Fase 0 (fondasi), Fase 1 (auth, peran, pengguna, semester), Fase 2 (mata kuliah & materi), Fase 3 (tugas), dan Fase 4 (kuis)** selesai.
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

Login menerima email **atau** NIM/NIDN. Data demo berisi 4 mata kuliah (dosen1 mengampu Algoritma dan Pemrograman serta Pemrograman Web;
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

## Catatan produksi (akan dilengkapi di Fase 6)

PostgreSQL, Redis + Horizon (Linux), disk `s3`/R2, HTTPS, catatan uji beban kuis 1.000 mahasiswa serentak.
