# Aksaraloka

LMS web multi-tenant untuk SMP, SMA/SMK, dan perguruan tinggi di Indonesia. Laravel 13, Livewire 4, Tailwind CSS v4, mobile-first.

> Status: **Fase 0 (fondasi) dan Fase 1 (multi-tenant & auth)** selesai. Rencana lengkap ada di prompt awal proyek; keputusan teknis di [docs/decisions.md](docs/decisions.md).

## Menjalankan di lokal

Prasyarat: PHP 8.3+, Composer, Node 20+, database (MySQL/PostgreSQL; SQLite untuk test).

```sh
composer install && npm install
cp .env.example .env && php artisan key:generate
# atur DB_* di .env, lalu:
php artisan migrate --seed
composer dev        # server + queue + vite
```

### Subdomain tenant

Setiap institusi diakses lewat `{subdomain}.aksaraloka.test`. Atur `APP_URL` dan `TENANT_ROOT_DOMAIN` (default `aksaraloka.test`).

- **Laravel Herd / Valet**: situs `aksaraloka.test` otomatis melayani semua subdomain.
- **`php artisan serve`**: tambahkan ke file hosts (`C:\Windows\System32\drivers\etc\hosts`):
  `127.0.0.1 aksaraloka.test smpn1bandung.aksaraloka.test kampusdemo.aksaraloka.test`
  lalu buka `http://smpn1bandung.aksaraloka.test:8000`.

Host selain `*.aksaraloka.test` (mis. `localhost`) dianggap domain utama (landing + panel super admin).

## Akun demo

Semua kata sandi: `password`. Hasil dari `php artisan db:seed` (DemoSeeder).

| Domain | Peran | Login |
| --- | --- | --- |
| `aksaraloka.test` | Super admin | `super@aksaraloka.test` |
| `smpn1bandung.aksaraloka.test` | Admin | `admin@smpn1bandung.test` |
| | Kepala sekolah | `kepala@smpn1bandung.test` |
| | Guru + wali kelas | `guru1@smpn1bandung.test` |
| | Guru | `guru2@smpn1bandung.test`, `guru3@smpn1bandung.test` |
| | Siswa (email) | `siswa1@smpn1bandung.test` … `siswa5@…` |
| | Siswa (NIS saja) | `2026006` … `2026030` |
| `kampusdemo.aksaraloka.test` | Admin | `admin@kampusdemo.test` |
| | Ketua prodi | `kepala@kampusdemo.test` |
| | Dosen + dosen wali | `guru1@kampusdemo.test` |
| | Dosen | `guru2@kampusdemo.test`, `guru3@kampusdemo.test` |
| | Mahasiswa (email) | `siswa1@kampusdemo.test` … `siswa5@…` |
| | Mahasiswa (NIM saja) | `2260006` … `2260030` |

Login menerima email **atau** NIS/NIM/NIP. Kelas, materi, tugas, dan kuis demo ditambahkan di fase berikutnya.

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

PostgreSQL, Redis + Horizon (Linux), disk `s3`/R2, HTTPS, wildcard DNS `*.domain`, catatan uji beban kuis 1.000 siswa serentak.
