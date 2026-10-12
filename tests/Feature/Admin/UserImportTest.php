<?php

use App\Enums\Role;
use App\Livewire\Admin\Users\Import;
use App\Models\ImportRun;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

const HEADER = ['name', 'identity_number', 'email', 'role', 'password'];

beforeEach(function () {
    Storage::fake('local');

    $this->admin = makeUser(Role::Admin);
    makeUser(Role::Student, ['identity_number' => '1111', 'email' => 'ada@contoh.test']);
});

function csv(array $rows): UploadedFile
{
    $quote = fn (string $v) => '"'.str_replace('"', '""', $v).'"';
    $content = implode("\n", array_map(fn ($r) => implode(',', array_map($quote, $r)), $rows));

    return UploadedFile::fake()->createWithContent('pengguna.csv', $content);
}

it('previews rows and flags invalid ones', function () {
    $file = csv([
        HEADER,
        ['Budi', '2001', 'budi@contoh.test', 'student', ''],
        ['Duplikat NIS', '1111', '', 'student', ''],
        ['Peran Salah', '2002', '', 'admin', ''],
        ['', '2003', 'bukan-email', 'guru', ''],
    ]);

    Livewire::actingAs($this->admin)->test(Import::class)
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

    Livewire::actingAs($this->admin)->test(Import::class)->set('file', $file)->call('import')->assertHasNoErrors();

    $run = ImportRun::firstOrFail();

    expect($run->status)->toBe('done')
        ->and($run->total_rows)->toBe(4)
        ->and($run->success_rows)->toBe(2)
        ->and($run->failed_rows)->toHaveCount(2)
        ->and($run->failed_rows[0]['row'])->toBe(4);

    $budi = User::where('identity_number', '2001')->firstOrFail();
    $siti = User::where('identity_number', '3001')->firstOrFail();

    expect($budi->hasAppRole(Role::Student))->toBeTrue()
        ->and($siti->roles->pluck('name')->sort()->values()->all())->toBe(['homeroom', 'teacher'])
        ->and(Hash::check('2001', $budi->password))->toBeTrue()      // tanpa password: NIS
        ->and(Hash::check('rahasia123', $siti->password))->toBeTrue()
        ->and(User::where('identity_number', '9999')->exists())->toBeFalse();
});

it('rejects duplicate identity numbers within the same file', function () {
    Livewire::actingAs($this->admin)->test(Import::class)
        ->set('file', csv([HEADER, ['A', '5001', '', 'student', ''], ['B', '5001', '', 'student', '']]))
        ->call('import');

    expect(User::where('identity_number', '5001')->count())->toBe(1)
        ->and(ImportRun::firstOrFail()->failed_rows)->toHaveCount(1);
});

it('blocks non-admins from importing', function () {
    $this->actingAs(makeUser(Role::Teacher));

    $this->get('/admin/pengguna/impor')->assertForbidden();
    $this->get('/admin/pengguna/impor/templat')->assertForbidden();
});

it('offers a CSV template to admins', function () {
    $this->actingAs($this->admin)
        ->get('/admin/pengguna/impor/templat')
        ->assertOk()->assertDownload('template-impor-pengguna.csv');
});

it('refuses files that are not csv or excel', function () {
    Livewire::actingAs($this->admin)->test(Import::class)
        ->set('file', UploadedFile::fake()->create('malware.php', 10, 'application/x-php'))
        ->assertHasErrors('file');
});
