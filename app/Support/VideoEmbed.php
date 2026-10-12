<?php

namespace App\Support;

class VideoEmbed
{
    /**
     * URL embed yang aman (domain tetap) untuk YouTube dan Vimeo, atau null bila bukan keduanya.
     */
    public static function embedUrl(string $url): ?string
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $host = preg_replace('/^(www\.|m\.)/', '', $host) ?? $host;
        $path = (string) ($parts['path'] ?? '');

        $youtube = null;

        if ($host === 'youtu.be') {
            $youtube = ltrim($path, '/');
        } elseif ($host === 'youtube.com') {
            parse_str((string) ($parts['query'] ?? ''), $query);
            $youtube = $query['v'] ?? (preg_match('#^/(?:embed|shorts)/([^/]+)#', $path, $m) ? $m[1] : null);
        }

        if (is_string($youtube) && preg_match('/^[A-Za-z0-9_-]{6,15}$/', $youtube)) {
            return 'https://www.youtube-nocookie.com/embed/'.$youtube;
        }

        if ($host === 'vimeo.com' && preg_match('#^/(\d{5,12})$#', $path, $m)) {
            return 'https://player.vimeo.com/video/'.$m[1];
        }

        return null;
    }
}
