<?php

use App\Support\Terminology;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

it('renders the landing page in Indonesian with the anti-flash theme script', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('lang="id"', false)
        ->assertSee('_x_theme', false)
        ->assertSee('Masuk');
});

it('does not expose the component gallery outside local', function () {
    $this->get('/ui')->assertNotFound();
});

it('renders the component gallery view with every component', function () {
    view()->share('errors', new ViewErrorBag);

    $html = view('ui')->render();

    expect($html)
        ->toContain('Tombol')
        ->toContain('tomSelect')
        ->toContain('datePicker')
        ->toContain('richEditor')
        ->toContain('filePicker')
        ->toContain('themedChart')
        ->toContain('pdfViewer')
        ->toContain('sortableList')
        ->toContain('role="dialog"')
        ->toContain('role="tablist"');
});

it('renders task status badges with consistent labels', function (string $status, string $label) {
    $this->blade('<x-status-badge :status="$status" />', ['status' => $status])->assertSee($label);
})->with([
    ['not_submitted', 'Belum dikumpulkan'],
    ['submitted', 'Dikumpulkan'],
    ['late', 'Terlambat'],
    ['graded', 'Dinilai'],
]);

it('renders lucide icons inline and hides them from assistive tech', function () {
    $this->blade('<x-icon name="home" class="size-4" />')
        ->assertSee('<svg', false)
        ->assertSee('aria-hidden="true"', false)
        ->assertDontSee('lucide-home', false);
});

it('shows validation errors on form fields with accessible markup', function () {
    $errors = (new ViewErrorBag)->put('default', new MessageBag(['name' => ['Nama wajib diisi.']]));

    view()->share('errors', $errors);

    $this->blade('<x-input name="name" label="Nama" />')
        ->assertSee('aria-invalid="true"', false)
        ->assertSee('Nama wajib diisi.')
        ->assertSee('for="name"', false);
});

it('switches terminology between school and campus', function () {
    expect(term('teacher'))->toBe('Guru')
        ->and(term('student', Terminology::CAMPUS))->toBe('Mahasiswa')
        ->and(term('section', Terminology::CAMPUS))->toBe('Pertemuan')
        ->and(term('section', Terminology::SCHOOL))->toBe('Topik');
});

it('uses campus terms when the bound tenant is a campus', function () {
    app()->instance('tenant', (object) ['level' => 'campus']);

    expect(term('teacher'))->toBe('Dosen');
});

it('keeps design tokens declared for both themes', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    foreach (['bg', 'surface', 'surface-muted', 'border', 'text', 'text-muted', 'primary', 'primary-fg', 'success', 'warning', 'danger'] as $token) {
        expect(substr_count($css, "--color-{$token}:"))->toBeGreaterThanOrEqual(2, "token {$token} must exist in light and dark");
    }
});
