<?php

namespace App\Actions\Announcements;

use App\Actions\Notifications\Notifier;
use App\Models\Announcement;
use App\Models\User;
use Mews\Purifier\Facades\Purifier;

class SaveAnnouncement
{
    /**
     * Membuat atau memperbarui pengumuman. Isi disanitasi (Purifier). Penerima notifikasi hanya saat pengumuman
     * dibuat (bukan saat diubah). Hak akses diperiksa pemanggil.
     *
     * @param  array{course_id: int|null, title: string, body: string, is_pinned: bool}  $data
     */
    public function handle(User $author, array $data, ?Announcement $announcement = null): Announcement
    {
        $attributes = [
            'title' => trim($data['title']),
            'body' => Purifier::clean($data['body'], 'content'),
            'is_pinned' => $data['is_pinned'],
        ];

        if ($announcement) {
            $announcement->update($attributes);

            return $announcement;
        }

        $created = Announcement::create($attributes + ['course_id' => $data['course_id'], 'user_id' => $author->id]);

        app(Notifier::class)->announcementPosted($created);

        return $created;
    }
}
