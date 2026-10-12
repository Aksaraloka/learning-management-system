# Aksaraloka

LMS web untuk **satu** sekolah (SMP, SMA/SMK) atau perguruan tinggi di Indonesia. Laravel 13, Livewire 4, Tailwind CSS v4, mobile-first.

> Status: **Fase 0 (fondasi), Fase 1 (auth, peran, pengguna, tahun ajaran), dan Fase 2 (kelas & materi)** selesai. Aplikasi melayani **satu institusi**
> (versi multi-tenant sudah dihapus; lihat [docs/decisions.md](docs/decisions.md)). Rencana lengkap ada di prompt awal proyek.

## Menjalankan di lokal

Prasyarat: PHP 8.3+, Composer, Node 20+, database (MySQL/PostgreSQL; SQLite untuk test).

```sh
composer install && npm install
cp .env.example .env && php artisan key:generate
# atur DB_*, INSTITUTION_NAME, INSTITUTION_LEVEL di .env, lalu:
php artisan migrate --seed     # --seed = data demo
composer dev                   # server + queue + vite
```

Buka <http://localhost:8000>. Tidak butuh domain atau subdomain.

### Institusi

Diatur lewat `.env`:

```dotenv
INSTITUTION_NAME="SMP Negeri 1 Bandung"
INSTITUTION_LEVEL=school   # school = SMP/SMA/SMK, campus = perguruan tinggi
```

`INSTITUTION_LEVEL` menentukan istilah di UI: Guru/Dosen, Siswa/Mahasiswa, Topik/Pertemuan, NIS/NIM.

### Produksi / tanpa data demo

```sh
php artisan migrate --force
php artisan aksaraloka:install --name="Admin TU" --email=admin@sekolah.sch.id   # kata sandi acak ditampilkan sekali
```

## Akun demo

Semua kata sandi: `password`. Hasil `php artisan db:seed` (DemoSeeder).

| Peran | Login |
| --- | --- |
| Admin | `admin@aksaraloka.test` |
| Kepala sekolah / ketua prodi | `kepala@aksaraloka.test` |
| Guru + wali kelas | `guru1@aksaraloka.test` |
| Guru | `guru2@aksaraloka.test`, `guru3@aksaraloka.test` |
| Siswa (email) | `siswa1@aksaraloka.test` … `siswa5@aksaraloka.test` |
| Siswa (NIS saja) | `2026006` … `2026030` (jenjang `campus`: NIM `2260006` … `2260030`) |

Login menerima email **atau** NIS/NIM/NIP. Data demo berisi 4 kelas (guru1 mengampu Matematika & IPS; siswa1–5 ikut semua kelas) lengkap dengan topik dan materi (teks, PDF, tautan, video). Tugas dan kuis demo ditambahkan di fase berikutnya.

## Kelas & materi

- **Kelas** (mata pelajaran / mata kuliah) dibuat guru atau admin dan ditautkan ke tahun ajaran. Setiap kelas punya **kode gabung** 6 karakter; siswa bergabung lewat menu Kelas, atau pengajar menambahkan banyak peserta sekaligus di halaman Anggota (tempel NIS/NIM/email).
- **Topik** (sekolah) atau **pertemuan 1–16** (kampus) berisi materi: teks (editor Tiptap + rumus KaTeX), berkas (PDF dan gambar dapat dipratinjau), tautan, dan video (YouTube/Vimeo dimuat setelah tombol putar ditekan). Topik dan materi dapat diurutkan dengan drag & drop.
- **Hak akses**: admin dan pengajar kelas mengelola; siswa membaca kelas yang diikutinya; kepala sekolah melihat semua kelas (hanya baca). Berkas disimpan privat dan hanya disajikan kepada anggota kelas.

### Batas unggah berkas

Maksimal 50 MB per berkas (`config/courses.php`), tetapi tidak pernah melebihi batas PHP. Halaman unggah menampilkan batas yang sebenarnya berlaku.
Bawaan PHP hanya 2 MB, jadi untuk 50 MB ubah `php.ini` (Herd: `%USERPROFILE%\.config\herd\bin\php84\php.ini`) lalu restart server:

```ini
upload_max_filesize = 64M
post_max_size = 64M
```

Berkas disimpan di disk `COURSE_DISK` (default `local`, folder `storage/app/private/courses`). Ganti ke `s3` untuk S3/Cloudflare R2.
## Impor pengguna

Admin: **Pengguna → Impor pengguna**. Unduh templat CSV, isi kolom `name, identity_number, email, role, password`
(`role` boleh beberapa, dipisah `|`, mis. `teacher|homeroom`; bisa juga `guru`, `siswa`, `dosen`, `mahasiswa`).
Kata sandi kosong → kata sandi awal = NIS/NIM/NIP. Peran `admin` tidak dapat diimpor. Pemrosesan lewat antrean (queue `imports`);
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

PostgreSQL, Redis + Horizon (Linux), disk `s3`/R2, HTTPS, catatan uji beban kuis 1.000 siswa serentak.
