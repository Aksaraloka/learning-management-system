<?php

namespace App\Support\Academic;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Klien OAuth2 authorization code + PKCE (S256) ke Academic, ditulis manual dengan `Http` (tanpa Socialite).
 * Token pengguna dipakai sekali untuk `/me` dan tidak disimpan.
 */
class AcademicSso
{
    public const SESSION_KEY = 'academic_sso';

    /** Sesi tempat daftar aplikasi dari `/apps` disimpan setelah login (untuk pengalih aplikasi). */
    public const APPS_KEY = 'academic_apps';

    public function authorizationUrl(): string
    {
        $state = Str::random(40);
        $verifier = Str::random(64);

        session()->put(self::SESSION_KEY, ['state' => $state, 'verifier' => $verifier]);

        return config('academic.url').'/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => config('academic.sso.client_id'),
            'redirect_uri' => route('academic.callback'),
            'scope' => config('academic.sso.scope'),
            'state' => $state,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ]);
    }

    /**
     * Validasi callback, tukar code dengan token, lalu ambil profil `/me`.
     *
     * @return array<string, mixed>
     *
     * @throws AcademicException pesan sudah siap ditampilkan ke pengguna
     */
    public function profileFromCallback(Request $request): array
    {
        $saved = session()->pull(self::SESSION_KEY);

        if ($request->filled('error')) {
            throw new AcademicException(__('academic.sso.denied'));
        }

        $state = (string) $request->query('state', '');
        $code = (string) $request->query('code', '');

        if (! is_array($saved) || $state === '' || ! hash_equals((string) $saved['state'], $state) || $code === '') {
            throw new AcademicException(__('academic.sso.invalid_state'));
        }

        $token = $this->exchangeCode($code, (string) $saved['verifier']);
        $profile = $this->fetchProfile($token);

        $this->rememberApps($token);

        return $profile;
    }

    /**
     * Simpan daftar aplikasi Aksaraloka milik pengguna ini di sesi. Kegagalan apa pun diabaikan: login tetap
     * berhasil dan pengalih cukup menampilkan tautan ke Academic.
     */
    private function rememberApps(string $token): void
    {
        session()->forget(self::APPS_KEY);

        try {
            $response = $this->http()->withToken($token)->get(config('academic.url').'/api/integration/v1/apps');
        } catch (ConnectionException) {
            return;
        }

        $rows = $response->json('data');

        if (! $response->successful() || ! is_array($rows)) {
            return;
        }

        $apps = [];

        foreach ($rows as $row) {
            if (is_array($row) && is_string($row['key'] ?? null) && is_string($row['label'] ?? null)
                && is_string($row['url'] ?? null) && preg_match('#^https?://#i', $row['url']) === 1) {
                $apps[] = ['key' => $row['key'], 'label' => $row['label'], 'url' => rtrim($row['url'], '/')];
            }
        }

        session()->put(self::APPS_KEY, $apps);
    }

    private function exchangeCode(string $code, string $verifier): string
    {
        try {
            $response = $this->http()->asForm()->post(config('academic.url').'/oauth/token', [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => route('academic.callback'),
                'client_id' => config('academic.sso.client_id'),
                'client_secret' => config('academic.sso.client_secret'),
                'code_verifier' => $verifier,
            ]);
        } catch (ConnectionException) {
            throw new AcademicException(__('academic.errors.unreachable'));
        }

        $token = $response->json('access_token');

        if (! $response->successful() || ! is_string($token) || $token === '') {
            throw new AcademicException(__('academic.sso.token_failed'), $response->status());
        }

        return $token;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchProfile(string $token): array
    {
        try {
            $response = $this->http()->withToken($token)->get(config('academic.url').'/api/integration/v1/me');
        } catch (ConnectionException) {
            throw new AcademicException(__('academic.errors.unreachable'));
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw new AcademicException(__('academic.sso.forbidden'), $response->status());
        }

        $profile = $response->json('data') ?? $response->json();

        if (! $response->successful() || ! is_array($profile) || ! isset($profile['id'])) {
            throw new AcademicException(__('academic.sso.profile_failed'), $response->status());
        }

        return $profile;
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout((int) config('academic.http.timeout'))
            ->connectTimeout((int) config('academic.http.connect_timeout'));
    }
}
