<?php

use App\Enums\Role;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

it('serves a valid web app manifest with install-ready icons', function () {
    $response = $this->get('/manifest.webmanifest')->assertOk()->assertHeader('Content-Type', 'application/manifest+json');
    $manifest = $response->json();

    expect($manifest['display'])->toBe('standalone')->and($manifest['lang'])->toBe('id')->and($manifest['start_url'])->toBe('/')
        ->and($manifest['name'])->toBe(institution_name())->and($manifest['theme_color'])->toBe('#c62828');

    $sizes = collect($manifest['icons'])->map(fn ($i) => $i['sizes'].':'.$i['purpose'])->all();

    expect($sizes)->toContain('192x192:any')->toContain('512x512:any')->toContain('512x512:maskable');

    foreach ($manifest['icons'] as $icon) {
        expect(is_file(public_path($icon['src'])))->toBeTrue($icon['src']);
    }
});

it('links the manifest and icons from every page, for guests too', function () {
    $this->get('/login')->assertOk()->assertSee('rel="manifest" href="/manifest.webmanifest"', false)->assertSee('apple-touch-icon', false);
    $this->actingAs(makeUser(Role::Student))->get('/')->assertSee('rel="manifest"', false);
});

it('serves a self-contained offline page without login', function () {
    $html = $this->get('/offline')->assertOk()->assertSee('Kamu sedang offline')->assertSee('Coba lagi')->getContent();

    // Harus berdiri sendiri: tidak boleh bergantung pada aset build yang mungkin tidak tersedia offline.
    expect($html)->not->toContain('/build/')->not->toContain('@vite');
});

it('ships a service worker that never caches pages or live updates', function () {
    $sw = file_get_contents(public_path('sw.js'));

    expect($sw)->toContain('/livewire')->toContain("request.method !== 'GET'")->toContain('/offline')
        ->toContain('materi\/\d+\/berkas')->toContain('clear-private');

    // Navigasi (HTML halaman, berisi data pribadi) tidak pernah ditulis ke cache.
    $navigate = Str::between($sw, 'async function navigate', 'async function networkFirstFile');

    expect($navigate)->not->toContain('.put(')->not->toContain('addAll');
});

it('clears offline material copies on logout', function () {
    $html = $this->actingAs(makeUser(Role::Student))->get('/')->getContent();

    expect($html)->toContain("caches.delete('aksaraloka-materials-v1')");
});

it('exposes the offline save button on file materials', function () {
    $teacher = makeUser(Role::Teacher);
    $course = makeCourse($teacher);
    $material = makeMaterial(makeSection($course), ['type' => 'file', 'file_path' => 'x.pdf', 'file_name' => 'x.pdf', 'file_mime' => 'application/pdf', 'file_size' => 10, 'body' => null]);

    $this->actingAs($teacher)->get(route('materials.show', ['course' => $course, 'material' => $material]))->assertOk()
        ->assertSee('offlineSave', false)->assertSee('Simpan untuk offline');
});

it('registers the service worker only in production builds', function () {
    $app = file_get_contents(resource_path('js/app.js'));

    expect($app)->toContain("register('/sw.js'")->toContain('import.meta.env.PROD');
});

it('shows friendly Indonesian error pages', function () {
    $this->get('/halaman-yang-tidak-ada')->assertNotFound()->assertSee('Halaman tidak ditemukan')->assertSee('Ke beranda');

    $student = makeUser(Role::Student);
    $this->actingAs($student)->get(route('admin.users.index'))->assertForbidden()->assertSee('Tidak punya akses');
});

it('renders every error page template', function () {
    foreach ([403, 404, 419, 429, 500, 503] as $code) {
        $html = view("errors.{$code}")->render();

        expect($html)->toContain(__("errors.{$code}.title"))->and(__("errors.{$code}.title"))->not->toStartWith('errors.');
    }
});

it('lets only admins into the Horizon dashboard gate', function () {
    expect(Gate::forUser(makeUser(Role::Admin))->allows('viewHorizon'))->toBeTrue()
        ->and(Gate::forUser(makeUser(Role::Teacher))->allows('viewHorizon'))->toBeFalse()
        ->and(Gate::forUser(makeUser(Role::Principal))->allows('viewHorizon'))->toBeFalse()
        ->and(Gate::allows('viewHorizon'))->toBeFalse();
});
