<?php

use App\Support\Tenancy;

it('resolves the tenant from the subdomain', function () {
    $tenant = makeTenant(['name' => 'SMP Contoh', 'subdomain' => 'smpcontoh']);

    $this->get($tenant->url('/login'))->assertOk()->assertSee('SMP Contoh');

    expect(Tenancy::id())->toBe($tenant->id);
});

it('shows the platform on the root domain without a tenant', function () {
    $this->get('http://aksaraloka.test/')->assertOk()->assertSee('Aksaraloka');

    expect(Tenancy::current())->toBeNull();
});

it('treats unknown hosts like localhost as the root domain', function () {
    $this->get('http://localhost/')->assertOk();

    expect(Tenancy::current())->toBeNull();
});

it('returns 404 for an unknown subdomain', function () {
    $this->get('http://tidak-ada.aksaraloka.test/login')->assertNotFound();
});

it('returns 403 for a suspended tenant', function () {
    $tenant = makeTenant(['subdomain' => 'mati', 'status' => 'suspended']);

    $this->get($tenant->url('/login'))->assertForbidden();
});

it('does not treat reserved or nested subdomains as tenants', function () {
    $this->get('http://www.aksaraloka.test/')->assertOk();

    expect(Tenancy::current())->toBeNull();
});

it('switches context between consecutive requests', function () {
    $a = makeTenant(['name' => 'Sekolah A', 'subdomain' => 'sekolah-a']);
    $b = makeTenant(['name' => 'Sekolah B', 'subdomain' => 'sekolah-b']);

    $this->get($a->url('/login'))->assertSee('Sekolah A')->assertDontSee('Sekolah B');
    $this->get($b->url('/login'))->assertSee('Sekolah B')->assertDontSee('Sekolah A');
    $this->get('http://aksaraloka.test/')->assertDontSee('Sekolah A');
});

it('uses campus terminology for a campus tenant', function () {
    $campus = makeTenant(['subdomain' => 'kampus', 'level' => 'campus']);

    Tenancy::bind($campus);

    expect(term('teacher'))->toBe('Dosen')->and(term('student'))->toBe('Mahasiswa');
});
