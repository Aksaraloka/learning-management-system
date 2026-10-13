<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationOpenController extends Controller
{
    /**
     * Menandai notifikasi milik pengguna sebagai dibaca lalu membuka tujuannya (id milik orang lain = 404).
     */
    public function __invoke(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $url = (string) ($notification->data['url'] ?? '/');

        // Tujuan selalu path internal (lihat Notice); apa pun yang bukan path relatif dialihkan ke beranda.
        return redirect()->to(str_starts_with($url, '/') && ! str_starts_with($url, '//') ? $url : '/');
    }
}
