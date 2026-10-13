<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class ManifestController extends Controller
{
    /**
     * Manifest PWA (dapat dipasang di layar utama). Nama universitas diambil dari konfigurasi.
     */
    public function __invoke(): JsonResponse
    {
        $name = institution_name();

        return response()->json([
            'name' => $name,
            'short_name' => 'Aksaraloka',
            'description' => __('ui.app.tagline'),
            'lang' => 'id',
            'dir' => 'ltr',
            'start_url' => '/',
            'scope' => '/',
            'id' => '/',
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => '#ffffff',
            'theme_color' => '#c62828',
            'icons' => [
                ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => [
                ['name' => __('ui.nav.tasks'), 'url' => '/tugas', 'icons' => [['src' => '/icons/icon-192.png', 'sizes' => '192x192']]],
                ['name' => __('ui.nav.notifications'), 'url' => '/notifikasi', 'icons' => [['src' => '/icons/icon-192.png', 'sizes' => '192x192']]],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
