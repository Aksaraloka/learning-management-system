<?php

namespace App\Support\Academic;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Klien API integrasi Academic v1 dengan token klien server (`client_credentials`). Token di-cache sampai
 * sesaat sebelum kedaluwarsa. Learn tidak pernah membaca database Academic; semua data lewat kelas ini.
 */
class AcademicClient
{
    public const TOKEN_CACHE_KEY = 'academic:server-token';

    /** Token dianggap kedaluwarsa sekian detik lebih awal agar tidak dipakai di ujung masa berlakunya. */
    private const TOKEN_LEEWAY_SECONDS = 60;

    private const MAX_PAGES = 500;

    /**
     * @return list<array<string, mixed>>
     */
    public function terms(): array
    {
        return $this->paginated('/terms');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function classes(int|string $termId): array
    {
        return $this->paginated("/terms/{$termId}/classes");
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function roster(int|string $classId): array
    {
        return $this->paginated("/classes/{$classId}/roster");
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function attendance(int|string $classId): array
    {
        return $this->paginated("/classes/{$classId}/attendance");
    }

    /**
     * Cari satu kelas (kontrak v1 tidak punya endpoint kelas tunggal, jadi dicari lewat daftar kelas per
     * semester; semester aktif dan terbaru lebih dulu). Null bila tidak ada di Academic.
     *
     * @return array{term: array<string, mixed>, class: array<string, mixed>}|null
     */
    public function findClass(int|string $classId, ?int $termId = null): ?array
    {
        $terms = $this->terms();
        $priority = fn (array $t): array => [(int) (($t['id'] ?? null) === $termId), (int) ($t['is_active'] ?? false), (string) ($t['starts_at'] ?? '')];
        usort($terms, fn (array $a, array $b) => $priority($b) <=> $priority($a));

        foreach ($terms as $term) {
            foreach ($this->classes($term['id']) as $class) {
                if ((string) ($class['id'] ?? '') === (string) $classId) {
                    return ['term' => $term, 'class' => $class];
                }
            }
        }

        return null;
    }

    /**
     * Kirim skor satu komponen nilai.
     *
     * @param  list<array{user_id: int|string, score: float|int}>  $scores
     * @return array{updated: int, skipped: list<array<string, mixed>>}
     *
     * @throws AcademicConflictException nilai terkunci atau lewat batas
     * @throws AcademicValidationException
     */
    public function pushGrades(int|string $classId, string $component, int|string $submittedBy, array $scores): array
    {
        $json = $this->request('POST', "/classes/{$classId}/grades", [
            'component' => $component,
            'submitted_by' => $submittedBy,
            'scores' => $scores,
        ])->json();

        $json = is_array($json) ? ($json['data'] ?? $json) : [];

        return [
            'updated' => (int) ($json['updated'] ?? 0),
            'skipped' => array_values((array) ($json['skipped'] ?? [])),
        ];
    }

    /**
     * Ikuti semua halaman (`data/meta`) hingga habis. Respons tanpa paginasi (daftar polos) juga diterima.
     *
     * @return list<array<string, mixed>>
     */
    private function paginated(string $path): array
    {
        $items = [];
        $perPage = (int) config('academic.http.per_page');

        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $body = $this->request('GET', $path, ['page' => $page, 'per_page' => $perPage])->json();

            if (! is_array($body)) {
                throw new AcademicException(__('academic.errors.unexpected_response'));
            }

            if (array_is_list($body)) {
                return $body;
            }

            array_push($items, ...array_values((array) ($body['data'] ?? [])));

            if ($page >= (int) ($body['meta']['last_page'] ?? 1)) {
                break;
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function request(string $method, string $path, array $payload = []): Response
    {
        $response = $this->send($method, $path, $payload);

        // Token bisa dicabut sebelum waktunya: ambil yang baru sekali lalu ulangi.
        if ($response->status() === 401) {
            Cache::forget(self::TOKEN_CACHE_KEY);
            $response = $this->send($method, $path, $payload);
        }

        return $this->ensureSuccessful($response);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(string $method, string $path, array $payload): Response
    {
        $http = $this->http()->withToken($this->token());

        try {
            return $method === 'GET'
                ? $http->get($path, $payload)
                : $http->asJson()->send($method, $path, ['json' => $payload]);
        } catch (ConnectionException) {
            throw new AcademicException(__('academic.errors.unreachable'));
        }
    }

    private function ensureSuccessful(Response $response): Response
    {
        if ($response->successful()) {
            return $response;
        }

        $message = (string) ($response->json('message') ?? '');

        throw match (true) {
            $response->status() === 409 => new AcademicConflictException($message !== '' ? $message : __('academic.errors.conflict'), 409),
            $response->status() === 422 => new AcademicValidationException($message !== '' ? $message : __('academic.errors.invalid'), (array) ($response->json('errors') ?? [])),
            default => new AcademicException($message !== '' ? $message : __('academic.errors.failed', ['status' => $response->status()]), $response->status()),
        };
    }

    private function token(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $response = Http::asForm()->acceptJson()
                ->timeout((int) config('academic.http.timeout'))
                ->connectTimeout((int) config('academic.http.connect_timeout'))
                ->post(config('academic.url').'/oauth/token', [
                    'grant_type' => 'client_credentials',
                    'client_id' => config('academic.server.client_id'),
                    'client_secret' => config('academic.server.client_secret'),
                    'scope' => config('academic.server.scope'),
                ]);
        } catch (ConnectionException) {
            throw new AcademicException(__('academic.errors.unreachable'));
        }

        $token = $response->json('access_token');

        if (! $response->successful() || ! is_string($token) || $token === '') {
            throw new AcademicException(__('academic.errors.token_failed'), $response->status());
        }

        Cache::put(self::TOKEN_CACHE_KEY, $token, max(1, (int) $response->json('expires_in', 3600) - self::TOKEN_LEEWAY_SECONDS));

        return $token;
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(config('academic.url').'/api/integration/v1')
            ->acceptJson()
            ->timeout((int) config('academic.http.timeout'))
            ->connectTimeout((int) config('academic.http.connect_timeout'))
            ->retry(
                (int) config('academic.http.retries') + 1,
                (int) config('academic.http.retry_delay_ms'),
                fn (Throwable $e) => $e instanceof ConnectionException
                    || ($e instanceof RequestException && $e->response->serverError()),
                throw: false,
            );
    }
}
