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
10. **Warna status** memakai token `success/warning/danger/primary` dengan latar transparan 15%; nilai token sudah dipilih agar kontras â‰¥ 4.5:1.
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

## Fase 2

40. **Kode gabung** 6 karakter dari alfabet tanpa 0/O/1/I/L; unik termasuk kelas yang sudah dihapus; dinormalisasi (huruf besar, tanda baca dibuang) saat dimasukkan. Percobaan salah dibatasi 10 per menit per akun. Pengajar dapat membuat kode baru (kode lama langsung tidak berlaku).
41. **Hak akses kelas**: admin dan pengajar kelas mengelola; siswa hanya kelas yang diikutinya; kepala sekolah melihat semua kelas termasuk daftar peserta tetapi tidak mengubah; guru lain tidak bisa melihat kelas yang bukan miliknya. Siswa hanya melihat pengajar, bukan daftar teman sekelas (privasi).
42. **Pendaftaran peserta**: lewat kode gabung (siswa) atau tempel daftar NIS/NIM/NIP/email (pengajar/admin). Akun harus punya peran yang sesuai (guru untuk pengajar, siswa untuk peserta). **Impor kelas lewat CSV (bagian F-02) ditunda**: kebutuhan pendaftaran massal sudah terpenuhi oleh tempel daftar; dapat ditambahkan bila diminta.
43. **Topik/pertemuan**: kampus dibatasi 16 pertemuan per mata kuliah (`config/courses.php`); sekolah tanpa batas. Judul kosong menjadi "Topik n"/"Pertemuan n". Menghapus topik menghapus materi dan berkasnya lalu merapikan urutan.
44. **Berkas materi privat**: disimpan di disk `COURSE_DISK` dengan nama acak (nama asli di database), hanya disajikan lewat `MaterialFileController` yang memeriksa policy, dengan `nosniff` dan CSP `sandbox`. PDF dan gambar inline; tipe lain diunduh. Validasi ganda: tipe dari isi berkas (`mimes`) **dan** ekstensi asli (`extensions`) harus ada di daftar putih; tipe yang dapat dieksekusi ditolak.
45. **Batas unggah efektif** = min(50 MB, `upload_max_filesize`, `post_max_size`); UI menampilkan nilai efektif agar tidak menjanjikan lebih dari server. Aturan unggah sementara Livewire dinaikkan ke 50 MB.
46. **Video**: hanya YouTube (`youtube-nocookie.com`) dan Vimeo yang disematkan, iframe baru dimuat setelah tombol putar ditekan (hemat data, privasi) dan di-sandbox; URL lain hanya tautan keluar. Tautan dibatasi `http(s)`.
47. **Teks materi**: profil Purifier `content` (p, br, hr, strong, em, u, s, code, pre, blockquote, h2, h3, ul, ol, li, a) dengan `rel=nofollow`/`target=_blank`; penanda rumus `$...$` dipertahankan untuk KaTeX.
48. **Penghapusan**: kelas soft delete (disembunyikan, berkas dipertahankan); topik dan materi dihapus permanen beserta berkasnya.
49. **Perbaikan `artisan serve` di Windows**: `serve` membuang variabel lingkungan di luar `passthroughVariables`, termasuk `TMP/TEMP`, sehingga setiap unggahan gagal dengan "unable to create a temporary file". `AppServiceProvider` menambahkan variabel itu (terbukti lewat uji browser nyata). Herd/php-fpm tidak terpengaruh.
50. **Instance library di luar state Alpine**: Proxy reaktif Alpine merusak field privat pdf.js/Tiptap/Chart.js; instance disimpan di closure. Ini juga memperbaiki pratinjau PDF galeri `/ui` (Fase 0) yang sebelumnya belum terverifikasi.
51. **Data demo kelas**: 4 kelas (sekolah: Matematika VII-A, IPA VII-A, Bahasa Indonesia VII-B, IPS VII-B; kampus: 4 mata kuliah dengan SKS), guru1 mengampu dua kelas, siswa1–5 mengikuti semua kelas. Berkas PDF contoh dibuat oleh seeder.

## Perubahan tema

52. **Dark mode berlatar hitam murni `#000000`** (menggantikan aturan awal "bukan hitam pekat"; permintaan pemilik produk). "Primary" ditafsirkan sebagai warna dasar/latar utama. Kartu `#0b0b0d`, permukaan redup `#17171a`, border `#2c2c31` agar elemen tetap terpisah dari latar; aksen (`--color-primary`) tetap `#818cf8` dengan teks `#000000`. Test `ThemeContrastTest` menjaga kontras teks >= 4.5:1 (WCAG AA) di kedua tema. Jika yang dimaksud adalah aksen/tombol berwarna hitam di dark mode, itu akan tak terlihat di atas latar hitam (kontras 1:1) sehingga tidak diterapkan.
53. **Aksen merah di kedua tema** (permintaan pemilik produk): terang `#c62828` (teks putih pada tombol), gelap `#ef4444` (teks hitam pada tombol); semua kombinasi lolos WCAG AA, dijaga `ThemeContrastTest`. Warna bahaya (`danger`) tetap merah juga sehingga mirip dengan aksen; aksi destruktif dibedakan lewat ikon, label, dan dialog konfirmasi, bukan warna saja. Seri ke-4 grafik memakai abu-abu (bukan `danger`) agar tidak kembar dengan aksen. `theme-color` browser diubah ke `#c62828`.

## Animasi (GSAP)

54. **GSAP inti saja, tanpa plugin** (permintaan pemilik produk: "gunakan GSAP untuk animasi yang cocok"). Pengguna utama memakai HP Android berkoneksi lambat, jadi animasi dibatasi pada yang bermanfaat dan murah: (a) `x-reveal` memunculkan kartu/daftar bertahap (fade + naik 12 px, maks. 12 elemen) pada daftar kelas, dashboard, dan daftar topik; (b) `x-countup` menaikkan angka statistik (angka sudah ada di HTML sehingga tetap benar tanpa JS); (c) denyut singkat pada item yang baru dipindah lewat drag & drop. Hanya `opacity` dan `transform`, dibersihkan (`clearProps`) setelah selesai.
55. **Tidak pernah menahan konten**: GSAP dimuat lazy (chunk `gsap-*.js`, ~26 KB gzip, di luar bundel utama ~96 KB gzip); jika belum siap dalam 600 ms elemen langsung ditampilkan tanpa animasi, dan animasi susulan dibatalkan agar tidak berkedip. Terverifikasi di browser dengan chunk GSAP sengaja ditunda 4 detik.
56. **Hormati `prefers-reduced-motion`**: animasi tidak dijalankan dan chunk GSAP tidak diunduh. Plugin yang dipertimbangkan namun tidak dipakai: Flip (urutan sudah ditangani SortableJS dan morph Livewire), Draggable/Inertia (SortableJS sudah cukup), ScrollTrigger/ScrollSmoother/SplitText (menambah bobot dan gerakan tanpa manfaat bagi LMS).

## Fase 3

57. **Kategori nilai** (`grade_categories`) dibuat di Fase 3 karena tugas menautkannya: tiap kelas otomatis mendapat Tugas 40 / Kuis 30 / Ujian 30 (bobot diubah di buku nilai, Fase 5). Migration mengisi kelas yang sudah ada.
58. **`assignments.course_id`** didenormalisasi dari `section_id` agar daftar tugas dan hak akses murah (tanpa join). Tugas soft delete (diarsipkan; kiriman tetap tersimpan); berkasnya baru dihapus permanen saat topik induknya dihapus.
59. **Status**: baris `submissions` bernilai `submitted | late | graded | returned`; "belum dikumpulkan" = tidak ada baris. Ditambah `returned` ("Perlu revisi") untuk fitur kembalikan-untuk-revisi. Badge "Dikumpulkan" memakai warna baru `info` (biru) karena aksen sekarang merah dan akan kembar dengan "Perlu revisi".
60. **Aturan pengumpulan**: sebelum tenggat boleh kirim/ganti; sesudah tenggat hanya bila `allow_late` (status Terlambat, `is_late` dicatat); setelah dinilai terkunci; dikembalikan = boleh kirim ulang kapan saja (status mengikuti waktu kirim). Minimal teks atau berkas. Satu berkas per kiriman (selaras model data PRD); lampiran guru hingga 10 berkas.
61. **Penilaian**: nilai 0..nilai maksimal (2 desimal). Tugas yang dikumpulkan di kertas tetap bisa dinilai (baris kosong dibuat). Mengembalikan kiriman menghapus nilai tetapi menyimpan umpan balik. **Penguncian semester**: bila `academic_terms.is_locked`, `AssignmentPolicy::grade` false dan action menolak; kepala sekolah hanya melihat. Log audit perubahan nilai dikerjakan di Fase 6 (`audit_logs`).
62. **Komentar**: publik (semua anggota kelas) atau privat. Komentar privat adalah percakapan satu siswa dengan pengajar (`thread_user_id`); siswa lain dan kepala sekolah tidak melihatnya, pengajar membaca/membalas di layar penilaian. Guru hanya menulis komentar publik di halaman tugas. Teks komentar tidak berformat dan di-escape saat tampil.
63. **Salin tugas** hanya ke kelas di tahun ajaran yang sama yang dikelola pengguna (admin: semua); tugas masuk ke topik pertama kelas tujuan (dibuat bila belum ada), kategori dicocokkan menurut nama, lampiran diduplikasi ke berkas baru, kiriman tidak disalin.
64. **Lampiran** ditambahkan setelah tugas disimpan (halaman ubah), satu per satu lewat FilePond yang mengosongkan dirinya (`filepond-clear`), memakai aturan unggah yang sama dengan materi (ukuran efektif, `mimes` + `extensions`).
65. **Notifikasi** tugas baru, tenggat H-1, dan nilai keluar dikerjakan di Fase 5 sesuai rencana; dashboard guru "kiriman belum dinilai" di Fase 6 (daftar Tugas sudah menampilkan jumlah yang perlu dinilai).

## Klarifikasi produk: khusus perguruan tinggi

66. **Aplikasi hanya untuk universitas** (klarifikasi pemilik produk; menggantikan mode ganda sekolah/kampus di PRD awal, #35, #43, #51 dan semua aturan "mode sekolah"). Dihapus: `INSTITUTION_LEVEL`, `App\Support\Terminology`, cabang `school`/`campus`, istilah Guru/Siswa/Topik/NIS/Wali Kelas/Kepala Sekolah. Yang berlaku: kamus tunggal di `lang/id/terms.php` (Dosen, Mahasiswa, Mata Kuliah, Pertemuan, Dosen Wali, Ketua Program Studi, NIM/NIDN, Semester, SKS) dan helper `term('key')` tanpa parameter mode.
67. **Aturan akademik jadi tetap**: SKS wajib 1-24 pada setiap mata kuliah; maksimal 16 pertemuan per mata kuliah (judul bawaan "Pertemuan n"); periode akademik ditampilkan sebagai "Semester" (mis. 2026/2027 Ganjil). Nama tabel/kolom tidak diubah (`courses`, `sections`, `academic_terms`) agar tidak ada migration perubahan nama; hanya label UI yang berubah.
68. **Data demo kampus**: akun `dosen1-3`, `mahasiswa1-5` (selebihnya login dengan NIM `2260006`-`2260030`), `kaprodi`, `admin`; mata kuliah Algoritma dan Pemrograman, Basis Data, Jaringan Komputer, Pemrograman Web. Alias impor lama (`guru`, `siswa`, `wali kelas`, `kepala sekolah`) tetap diterima agar templat lama tidak rusak.
69. **Perbaikan migration yang sudah rilis**: `2026_10_16_000002_create_assignments_tables` gagal di MySQL pada instalasi baru karena nama indeks otomatis `assignment_comments_assignment_id_is_private_thread_user_id_index` (>64 karakter). Diperbaiki di tempat dengan nama indeks eksplisit; aman untuk database yang sudah memigrasikannya (hanya nama indeks pada instalasi baru yang berbeda). Pelajaran: SQLite (test) tidak membatasi panjang nama, jadi indeks komposit panjang harus diberi nama eksplisit.

## Ikon

70. **Ikon Heroicons** (permintaan pemilik produk, menggantikan Lucide #3). Paket npm `heroicons` 2.2.0 dipasang sebagai dev dependency; SVG `24/outline` (varian bawaan) dan `16/solid` (ikon sangat kecil, mis. badge status) disalin ke `resources/icons/{outline,solid}` beserta lisensinya (MIT). `<x-icon name="..." [solid]>` merender SVG inline dan melempar exception di local/testing bila ikon tidak ada. Nama lama bergaya Lucide diterjemahkan oleh `config/icons.php` sehingga view lama tidak perlu diubah; kode baru memakai nama Heroicons langsung. `IconsTest` memindai kode agar setiap nama ikon yang dipakai benar-benar ada.

## Fase 4

71. **Model kuis**: `quizzes` (jadwal buka/tutup, durasi, acak soal/pilihan, maks. percobaan, jadwal tampil nilai), `questions` (4 tipe: pilihan ganda, benar/salah, isian singkat, esai; kunci di `answer_key`, pilihan di `options` JSON dengan id acak stabil), `quiz_attempts` (jawaban, tanda ragu-ragu, urutan soal/pilihan acak, nilai otomatis + manual, status `in_progress|submitted|graded`). Bank soal per kuis (bukan bank soal bersama lintas kuis). Indeks diberi nama eksplisit (lihat #69).
72. **Server menjadi acuan waktu**: `deadline_at = min(mulai + durasi, kuis ditutup)` dihitung dan disimpan saat mulai; sisa waktu selalu dihitung ulang dari database (`remainingSeconds()`), klien hanya menampilkan hitung mundur dan menyelaraskan diri dari balasan setiap simpan. Jawaban yang tiba hingga 10 detik setelah batas (`GRACE_SECONDS`, latensi jaringan) masih diterima; setelahnya ditolak dan percobaan diselesaikan. Percobaan yang ditinggalkan diselesaikan oleh `quizzes:finalize-expired` (scheduler tiap menit) dan oleh halaman pengerjaan saat dibuka kembali; waktu selesai tidak pernah melewati `deadline_at`.
73. **Kunci jawaban tidak pernah dikirim ke halaman mahasiswa**: halaman pengerjaan hanya menerima id/teks soal dan pilihan (`Question::optionsFor()` membuang penanda benar). Kunci dan rubrik esai baru terlihat di tinjauan: staf kapan saja; mahasiswa hanya bila nilai sudah boleh tampil (`show_result_at`) **dan** kuis sudah ditutup atau percobaannya habis. Gambar soal hanya disajikan ke staf dan mahasiswa yang sudah memulai kuis tersebut.
74. **Autosave**: seluruh soal dimuat sekali dan pindah soal terjadi di peramban (satu soal per layar di HP, nomor soal sebagai navigasi, tanda ragu-ragu). Jawaban dikirim satu per satu lewat method Livewire `#[Renderless]` (pilihan langsung, ketikan debounce 700 ms), disimpan dengan kunci baris (`lockForUpdate`) agar dua tab tidak saling menimpa, dan dicadangkan di `localStorage` (per percobaan) bersama daftar jawaban yang belum terkirim. Saat koneksi putus pengiriman diulang tiap 4 detik dan segera saat `online`; saat dimuat ulang, jawaban lokal yang belum terkirim dikirim ulang. Nilai tidak valid untuk tipe soal diperlakukan kosong (tidak pernah error di sisi peserta).
75. **Penilaian**: pilihan ganda dan benar/salah dicocokkan persis; isian singkat dinormalkan (spasi berlebih, huruf besar/kecil kecuali `case_sensitive`) dan boleh punya beberapa jawaban diterima; esai dinilai manual 0..poin soal. Percobaan tanpa esai langsung `graded`; dengan esai `submitted` sampai semua esai dinilai (nilai akhir kosong sebelum itu). Mengoreksi kunci setelah kuis dikerjakan tidak mengubah nilai otomatis; dosen memakai "Hitung ulang nilai". **Penguncian semester** berlaku seperti tugas (#61): penilaian esai ditolak bila `academic_terms.is_locked`; ketua program studi hanya melihat.
76. **Hak akses**: admin dan dosen pengampu mengelola kuis; hanya mahasiswa yang terdaftar sebagai peserta (dan kuis terbit) yang boleh mengerjakan; kuis draf tak terlihat mahasiswa; hanya pemilik yang membuka/menjawab percobaannya; ketua program studi membaca hasil. Hasil percobaan terbaru, bukan terbaik, ditampilkan per percobaan (agregasi nilai akhir mata kuliah dikerjakan di Fase 6 bersama buku nilai).
77. **Uji beban**: pendekatan dan angka target ada di `docs/load-testing.md`. Dibutuhkan Linux + PostgreSQL + Redis (tidak tersedia di mesin dev Windows), jadi pengukuran sebenarnya dilakukan di Fase 6 saat tahap produksi.