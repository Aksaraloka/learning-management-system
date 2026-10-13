<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi dalam aplikasi (kanal database). Satu kelas untuk semua jenis; jenisnya ada di `kind`
 * (assignment | quiz | announcement | grade | reminder) dan menentukan ikon di daftar notifikasi.
 */
class Notice extends Notification implements ShouldQueue
{
    use Queueable;

    /** Tujuan disimpan sebagai path relatif agar benar di domain/port mana pun dan tidak bisa mengarah ke situs lain. */
    public string $url;

    public function __construct(
        public string $kind,
        public string $title,
        public ?string $body,
        string $url,
    ) {
        $parts = parse_url($url) ?: [];

        $this->url = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{kind: string, title: string, body: string|null, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return ['kind' => $this->kind, 'title' => $this->title, 'body' => $this->body, 'url' => $this->url];
    }
}
