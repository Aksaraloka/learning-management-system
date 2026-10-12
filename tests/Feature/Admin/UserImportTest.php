<?php

use App\Enums\Role;
use App\Livewire\Admin\Users\Import;
use App\Models\ImportRun;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');

    $this->tenant = makeTenant(['slug' => 'smp1']);
    $this->admin = makeUser($this->tenant, Role::Admin);
    makeUser($this->tenant, Role::Student, ['identity_number' => '1111', 'email' => 'ada@contoh.test']);

    actingInTenant($this->tenant, $this->admin);
});

function csv(array $rows): UploadedFile
{
    $quote = fn (string $v) => '"'.str_replace('"', '""', $v).'"';
    $content = implode("\n", array_map(fn ($r) => implode(',', array_map($quote, $r)), $rows));

    return UploadedFile::fake()->createWithContent('pengguna.csv', $content);
}

const HEADER = ['name', 'identity_number', 'email', 'role', 'password'];

it('previews rows and flags invalid ones', function () {
    $file = csv([
        HEADER,
        ['Budi', '2001', 'budi@contoh.test', 'student', ''],
        ['Duplikat NIS', '1111', '', 'student', ''],
        ['Peran Salah', '2002', '', 'admin', ''],
        ['', '2003', 'bukan-email', 'guru', ''],
    ]);

    Livewire::test(Import::class)
        ->set('file', $file)
        ->assertSet('totalRows', 4)
        ->assertSet('invalidRows', 3)
        ->assertSee('sudah terdaftar')
        ->assertSee('tidak boleh diimpor');
});

it('imports valid rows in the queue and reports the failed ones', function () {
    $file = csv([
        HEADER,
        ['Budi Santoso', '2001', 'budi@contoh.test', 'siswa', ''],
        ['Siti, S.Pd.', '3001', 'siti@contoh.test', 'teacher|homeroom', 'rahasia123'],
        ['Duplikat NIS', '1111', '', 'student', ''],
        ['Mau Jadi Admin', '9999', '', 'admin', ''],
    ]);

    Livewire::test(Import::class)->set('file', $file)->call('import')->assertHasNoErrors();

    $run = ImportRun::firstOrFail();

    expect($run->status)->toBe('done')
        ->and($run->total_rows)->toBe(4)
        ->and($run->success_rows)->toBe(2)
        ->and($run->failed_rows)->toHaveCount(2)
        ->and($run->failed_rows[0]['row'])->toBe(4)
        ->and($run->tenant_id)->toBe($this->tenant->id);

    $budi = User::where('identity_number', '2001')->firstOrFail();
    $siti = User::where('identity_number', '3001')->firstOrFail();

    expect($budi->hasAppRole(Role::Student))->toBeTrue()
        ->and($siti->hasAppRole(Role::Teacher, Role::Homeroom))->toBeTrue()
        ->and($siti->roles->pluck('name')->sort()->values()->all())->toBe(['homeroom', 'teacher'])
        ->and($budi->tenant_id)->toBe($this->tenant->id)
        ->and(Hash::check('2001', $budi->password))->toBeTrue()      // tanpa password: NIS
        ->and(Hash::check('rahasia123', $siti->password))->toBeTrue()
        ->and(User::where('identity_number', '9999')->exists())->toBeFalse();
});

it('rejects duplicate identity numbers within the same file', function () {
    Livewire::test(Import::class)
        ->set('file', csv([HEADER, ['A', '5001', '', 'student', ''], ['B', '5001', '', 'student', '']]))
        ->call('import');

    expect(User::where('identity_number', '5001')->count())->toBe(1)
        ->and(ImportRun::firstOrFail()->failed_rows)->toHaveCount(1);
});

it('imports into the right tenant even when another tenant has the same NIS', function () {
    $other = makeTenant(['slug' => 'smp2']);
    makeUser($other, Role::Student, ['identity_number' => '7001']);

    Livewire::test(Import::class)->set('file', csv([HEADER, ['Baru', '7001', '', 'student', '']]))->call('import');

    expect(ImportRun::firstOrFail()->success_rows)->toBe(1)
        ->and(User::withoutGlobalScopes()->where('identity_number', '7001')->count())->toBe(2);
});

it('blocks non-admins from importing', function () {
    actingInTenant($this->tenant, makeUser($this->tenant, Role::Teacher));

    $this->get($this->tenant->url('/admin/pengguna/impor'))->assertForbidden();
    $this->get($this->tenant->url('/admin/pengguna/impor/templat'))->assertForbidden();
});

it('offers a CSV template to admins', function () {
    $this->actingAs($this->admin)
        ->get($this->tenant->url('/admin/pengguna/impor/templat'))
        ->assertOk()->assertDownload('template-impor-pengguna.csv');
});

it('refuses files that are not csv or excel', function () {
    Livewire::test(Import::class)
        ->set('file', UploadedFile::fake()->create('malware.php', 10, 'application/x-php'))
        ->assertHasErrors('file');
});

it('only shows own tenant import history', function () {
    $other = makeTenant(['slug' => 'smp2']);
    Tenancy::run($other, fn () => ImportRun::create([
        'user_id' => makeUser($other, Role::Admin)->id, 'type' => 'users', 'file_path' => 'x.csv', 'status' => 'done', 'total_rows' => 99, 'success_rows' => 99,
    ]));

    Tenancy::bind($this->tenant);

    expect(ImportRun::count())->toBe(0);
});
