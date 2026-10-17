<?php

namespace App\Support\Academic;

use App\Models\User;

/**
 * Daftar aplikasi Aksaraloka untuk pengalih di header. Sumbernya daftar `/apps` yang disimpan di sesi saat login SSO;
 * bila tidak ada (login darurat admin atau `/apps` gagal) hanya tautan ke Academic dari `ACADEMIC_URL`.
 */
class AppSwitcher
{
    public const CURRENT = 'lms';

    public const ACADEMIC = 'academic';

    /**
     * @return array<int, array{key: string, label: string, url: string}> kosong bila integrasi nonaktif
     */
    public function linksFor(?User $user): array
    {
        if (! $user || ! config('academic.enabled')) {
            return [];
        }

        $stored = session(AcademicSso::APPS_KEY);
        $apps = is_array($stored) ? $stored : [];
        $keys = array_column($apps, 'key');

        if (! in_array(self::ACADEMIC, $keys, true)) {
            array_unshift($apps, ['key' => self::ACADEMIC, 'label' => 'Academic', 'url' => (string) config('academic.url')]);
        }

        if (! in_array(self::CURRENT, $keys, true)) {
            $apps[] = ['key' => self::CURRENT, 'label' => 'Learn', 'url' => rtrim((string) config('app.url'), '/')];
        }

        return $apps;
    }

    /**
     * Academic dibuka langsung (sesinya sendiri); aplikasi lain lewat SSO-nya agar tanpa login ulang.
     *
     * @param  array{key: string, label: string, url: string}  $app
     */
    public function href(array $app): string
    {
        return $app['key'] === self::ACADEMIC ? $app['url'] : $app['url'].'/auth/academic';
    }
}
