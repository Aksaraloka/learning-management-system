<?php

use App\Support\Tenancy;

it('resolves the tenant from the first URL segment', function () {
    $tenant = makeTenant(['name' => 'SMP Contoh', 'slug' => 'smpcontoh']);

    $this->get('/smpcontoh/login')->assertOk()->assertSee('SMP Contoh');

    expect(Tenancy::id())->toBe($tenant->id);
});

it('generates tenant-prefixed URLs inside a tenant', function () {
    $tenant = makeTenant(['slug' => 'smpcontoh']);

    $this->get('/smpcontoh/login')
        ->assertSee('http://aksaraloka.test/smpcontoh/lupa-kata-sandi', false)
        ->assertSee('http://aksaraloka.test/smpcontoh/kebijakan-privasi', false);

    expect($tenant->url('/login'))->toBe('http://aksaraloka.test/smpcontoh/login')
        ->and($tenant->url())->toBe('http://aksaraloka.test/smpcontoh');
});

it('points the Livewire update endpoint at the current context', function () {
    makeTenant(['slug' => 'smpcontoh']);

    $this->get('/smpcontoh/login')->assertSee('update\/smpcontoh', false);
    $this->get('/platform/login')->assertSee('update\/platform', false)->assertDontSee('smpcontoh', false);
});

it('shows the platform under /platform without a tenant', function () {
    $this->get('/platform')->assertOk()->assertSee('Aksaraloka');
    $this->get('/platform/login')->assertOk();

    expect(Tenancy::current())->toBeNull();
});

it('redirects the bare root to the platform landing', function () {
    $this->get('/')->assertRedirect('http://aksaraloka.test/platform');
});

it('serves the tenant home at /{slug} and sends guests to login', function () {
    $tenant = makeTenant(['slug' => 'smpcontoh']);

    $this->get('/smpcontoh')->assertRedirect('http://aksaraloka.test/smpcontoh/login');

    expect(Tenancy::id())->toBe($tenant->id);
});

it('returns 404 for an unknown slug', function () {
    $this->get('/tidak-ada/login')->assertNotFound();
    $this->get('/tidak-ada')->assertNotFound();
});

it('returns 403 for a suspended tenant', function () {
    makeTenant(['slug' => 'mati', 'status' => 'suspended']);

    $this->get('/mati/login')->assertForbidden();
});

it('never treats reserved words as tenants', function (string $path) {
    $this->get($path)->assertNotFound();
})->with(['/admin', '/theme', '/profil/profil', '/livewire-abc12345/x', '/login/login']);

it('keeps platform routes out of tenant context', function () {
    makeTenant(['slug' => 'smpcontoh']);

    $this->get('/smpcontoh/platform/institusi')->assertNotFound();
});

it('switches context between consecutive requests', function () {
    makeTenant(['name' => 'Sekolah A', 'slug' => 'sekolah-a']);
    makeTenant(['name' => 'Sekolah B', 'slug' => 'sekolah-b']);

    $this->get('/sekolah-a/login')->assertSee('Sekolah A')->assertDontSee('Sekolah B');
    $this->get('/sekolah-b/login')->assertSee('Sekolah B')->assertDontSee('Sekolah A');
    $this->get('/platform/login')->assertDontSee('Sekolah A')->assertDontSee('Sekolah B');
});

it('uses campus terminology for a campus tenant', function () {
    $campus = makeTenant(['slug' => 'kampus', 'level' => 'campus']);

    Tenancy::bind($campus);

    expect(term('teacher'))->toBe('Dosen')->and(term('student'))->toBe('Mahasiswa');
});
