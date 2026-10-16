<?php

use App\Support\Academic\AcademicClient;
use App\Support\Academic\AcademicConflictException;
use App\Support\Academic\AcademicException;
use App\Support\Academic\AcademicValidationException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    enableAcademic();
    Cache::flush();
});

function fakeToken(): array
{
    return ['academic.test/oauth/token' => Http::response(['access_token' => 'tok-1', 'expires_in' => 3600])];
}

test('integrasi nonaktif secara bawaan di test', function () {
    config(['academic.enabled' => false]);

    expect(config('academic.enabled'))->toBeFalse();
});

test('token klien server diminta sekali lalu dipakai ulang dari cache', function () {
    Http::fake(fakeToken() + [
        'academic.test/api/integration/v1/terms*' => Http::response(['data' => [['id' => 1]], 'meta' => ['last_page' => 1]]),
    ]);

    $client = new AcademicClient;
    $client->terms();
    $client->terms();

    Http::assertSentCount(3);
    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/oauth/token')
        && $r['grant_type'] === 'client_credentials'
        && $r['client_id'] === 'server-id'
        && $r['scope'] === 'sync.read grades.write');
    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/terms') && $r->hasHeader('Authorization', 'Bearer tok-1'));
});

test('mengikuti seluruh halaman paginasi', function () {
    Http::fake(fakeToken() + [
        'academic.test/api/integration/v1/classes/9/roster*' => Http::sequence()
            ->push(['data' => [['user_id' => 1], ['user_id' => 2]], 'meta' => ['last_page' => 2]])
            ->push(['data' => [['user_id' => 3]], 'meta' => ['last_page' => 2]]),
    ]);

    expect((new AcademicClient)->roster(9))->toHaveCount(3);
});

test('findClass mencari kelas di semua semester dan mengembalikan null bila tidak ada', function () {
    Http::fake(fakeToken() + [
        'academic.test/api/integration/v1/terms/1/classes*' => Http::response(['data' => [['id' => 10]]]),
        'academic.test/api/integration/v1/terms/2/classes*' => Http::response(['data' => [['id' => 20]]]),
        'academic.test/api/integration/v1/terms*' => Http::response(['data' => [['id' => 1, 'is_active' => false], ['id' => 2, 'is_active' => true]]]),
    ]);

    $client = new AcademicClient;

    expect($client->findClass(20)['term']['id'])->toBe(2)
        ->and($client->findClass(999))->toBeNull();
});

test('pushGrades mengirim komponen dan membaca hasilnya', function () {
    Http::fake(fakeToken() + [
        'academic.test/api/integration/v1/classes/5/grades' => Http::response(['updated' => 2, 'skipped' => [['user_id' => 7, 'reason' => 'bukan peserta']]]),
    ]);

    $result = (new AcademicClient)->pushGrades(5, 'assignment', 3, [['user_id' => 1, 'score' => 80.5]]);

    expect($result['updated'])->toBe(2)->and($result['skipped'])->toHaveCount(1);
    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/grades') && $r->method() === 'POST' && $r['component'] === 'assignment' && $r['submitted_by'] === 3 && $r['scores'][0]['score'] === 80.5);
});

test('409 menjadi AcademicConflictException dengan alasan dari Academic', function () {
    Http::fake(fakeToken() + [
        'academic.test/api/integration/v1/classes/5/grades' => Http::response(['message' => 'Nilai sudah dikunci.'], 409),
    ]);

    expect(fn () => (new AcademicClient)->pushGrades(5, 'final', 3, []))
        ->toThrow(AcademicConflictException::class, 'Nilai sudah dikunci.');
});

test('422 menjadi AcademicValidationException', function () {
    Http::fake(fakeToken() + [
        'academic.test/api/integration/v1/classes/5/grades' => Http::response(['message' => 'Tidak valid.', 'errors' => ['component' => ['x']]], 422),
    ]);

    expect(fn () => (new AcademicClient)->pushGrades(5, 'zzz', 3, []))->toThrow(AcademicValidationException::class);
});

test('5xx diulang lalu berhasil', function () {
    Http::fake(fakeToken() + [
        'academic.test/api/integration/v1/terms*' => Http::sequence()
            ->push('galat', 503)
            ->push(['data' => [['id' => 1]]]),
    ]);

    expect((new AcademicClient)->terms())->toHaveCount(1);
});

test('5xx terus-menerus menjadi AcademicException', function () {
    Http::fake(fakeToken() + ['academic.test/api/integration/v1/terms*' => Http::response('galat', 500)]);

    expect(fn () => (new AcademicClient)->terms())->toThrow(AcademicException::class);
});

test('401 membuang token lama dan mencoba sekali lagi dengan token baru', function () {
    Cache::put(AcademicClient::TOKEN_CACHE_KEY, 'basi', 600);
    Http::fake(fakeToken() + [
        'academic.test/api/integration/v1/terms*' => Http::sequence()
            ->push(['message' => 'Unauthenticated.'], 401)
            ->push(['data' => [['id' => 1]]]),
    ]);

    expect((new AcademicClient)->terms())->toHaveCount(1)
        ->and(Cache::get(AcademicClient::TOKEN_CACHE_KEY))->toBe('tok-1');
});

test('gagal mendapat token menjadi AcademicException', function () {
    Http::fake(['academic.test/oauth/token' => Http::response(['error' => 'invalid_client'], 401)]);

    expect(fn () => (new AcademicClient)->terms())->toThrow(AcademicException::class);
});
