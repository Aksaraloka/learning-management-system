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
