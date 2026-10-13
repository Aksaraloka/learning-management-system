# Uji beban kuis (target 1.000 mahasiswa serentak)

> **Status: rencana, belum diukur.** Mesin pengembangan (Windows, MySQL tunggal, `artisan serve`) tidak mewakili produksi,
> sehingga angka sebenarnya diambil di Fase 6 pada lingkungan Linux + PostgreSQL + Redis. Dokumen ini menetapkan beban yang
> harus diuji, kriteria lulus, dan skrip k6 awal.

## Profil beban

Satu kuis, 1.000 mahasiswa, 40 soal, durasi 40 menit, semua mulai dalam jendela 1 menit (kasus terburuk: kuis dibuka pukul tertentu).

| Fase | Beban | Perkiraan |
| --- | --- | --- |
| Mulai | 1.000 `StartAttempt` + render halaman pengerjaan dalam 60 dtk | ±17 permintaan/dtk, tiap permintaan memuat seluruh soal sekali |
| Mengerjakan | tiap mahasiswa menyimpan jawaban rata-rata tiap 20–30 dtk (pilihan langsung, esai debounce 700 ms) | 35–50 permintaan/dtk rata-rata |
| Penutupan | banyak yang menekan Kumpulkan/waktu habis bersamaan; klien mengirim jawaban terakhir lalu `finish` | lonjakan ±5× rata-rata selama 1–2 menit |

Setiap `saveAnswer` melakukan: hidrasi komponen Livewire (1 query percobaan), pengecekan policy, 1 query soal, transaksi pendek dengan
`SELECT … FOR UPDATE` + `UPDATE quiz_attempts` (JSON `answers`). Tidak ada antrean/job pada jalur ini, jadi kapasitas ditentukan PHP-FPM dan database.

## Kriteria lulus

- `saveAnswer`: p95 < 300 ms, p99 < 800 ms, galat < 0,5 %.
- Halaman pengerjaan (mulai): p95 < 2 dtk, ukuran HTML < 150 KB untuk 40 soal tanpa gambar.
- Tidak ada deadlock; tidak ada jawaban hilang: jumlah jawaban di DB = jumlah yang dikirim klien uji.
- `quizzes:finalize-expired` menyelesaikan 1.000 percobaan kedaluwarsa < 60 dtk.
- Penggunaan CPU database < 70 % dan koneksi tidak habis pada beban puncak.

## Prasyarat lingkungan uji

- PHP-FPM dengan OPcache, `pm.max_children` disesuaikan memori (±50 MB per worker); 1 server aplikasi 4 vCPU/8 GB untuk awal.
- PostgreSQL (atau MySQL) terpisah; PgBouncer (mode transaction) bila koneksi menjadi batas.
- Sesi dan cache di Redis (`SESSION_DRIVER=redis`, `CACHE_STORE=redis`) agar tidak membebani DB.
- `APP_ENV=production`, `php artisan config:cache route:cache view:cache`, aset hasil `npm run build`.
- Data uji: `migrate:fresh --seed`, lalu buat 1.000 mahasiswa terdaftar pada satu mata kuliah (factory) dan satu kuis terbit.

## Skrip k6 (kerangka)

Livewire memakai satu endpoint `POST {update-uri}` dengan *snapshot* komponen. Skrip mengambil snapshot dari HTML halaman pengerjaan lalu
memanggil `saveAnswer` seperti klien sebenarnya. Perlu disesuaikan dengan versi Livewire (cek `data-update-uri` dan format payload
di tab Network peramban sebelum dijalankan) dan belum pernah dijalankan di sini.

```js
import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  scenarios: {
    quiz: { executor: 'ramping-vus', stages: [
      { duration: '1m', target: 1000 },   // semua mulai dalam 1 menit
      { duration: '10m', target: 1000 },  // mengerjakan
      { duration: '1m', target: 0 },
    ] },
  },
  thresholds: { http_req_failed: ['rate<0.005'], 'http_req_duration{name:save}': ['p(95)<300'] },
};

const BASE = __ENV.BASE_URL;

export default function () {
  const n = (__VU % 1000) + 1;
  const jar = http.cookieJar();

  // 1. Login (halaman login Livewire juga memakai snapshot; alternatif: buat sesi lewat rute uji khusus/seed token).
  //    ... isi sesuai hasil pengamatan di peramban ...

  // 2. Buka kuis -> mulai -> halaman pengerjaan.
  const page = http.get(`${BASE}/kelas/${__ENV.COURSE}/kuis/${__ENV.QUIZ}/percobaan/${__ENV.ATTEMPT_BASE_ID}`);
  const snapshot = page.body.match(/wire:snapshot="([^"]+)"/)[1].replace(/&quot;/g, '"');
  const updateUri = page.body.match(/data-update-uri="([^"]+)"/)[1];
  const csrf = page.body.match(/data-csrf="([^"]+)"/)[1];

  // 3. Simpan jawaban tiap 20-30 dtk.
  for (let i = 0; i < 20; i++) {
    const res = http.post(`${BASE}${updateUri}`, JSON.stringify({
      _token: csrf,
      components: [{ snapshot, updates: {}, calls: [{ path: '', method: 'saveAnswer', params: [Number(__ENV.FIRST_QUESTION) + (i % 40), 'true'] }] }],
    }), { headers: { 'Content-Type': 'application/json', 'X-Livewire': '' }, tags: { name: 'save' } });

    check(res, { 'saved': (r) => r.status === 200 });
    sleep(20 + Math.random() * 10);
  }
}
```

## Cara membaca hasil

1. Jalankan bertahap: 100 → 250 → 500 → 1.000 VU, catat p95 `save`, galat, CPU/RAM aplikasi dan DB.
2. Bandingkan jumlah jawaban di DB dengan jumlah yang dikirim (konsistensi).
3. Jika p95 melampaui target: tambah worker PHP-FPM, periksa query lambat (`quiz_attempts` memakai kunci `quiz_attempts_unique_attempt` dan `quiz_attempts_expiry_index`),
   pertimbangkan menaikkan jeda debounce esai (700 ms → 1,5 dtk) dan memisahkan DB baca/tulis.
4. Catat hasilnya di bagian bawah dokumen ini (tanggal, spesifikasi, angka).

## Hasil

_Belum ada pengukuran._
