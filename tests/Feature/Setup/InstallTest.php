<?php

use App\Actions\Setup\EnsureRoles;
use App\Enums\Role;
use App\Models\User;
use App\Support\Terminology;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role as RoleModel;

it('has every application role after migrating', function () {
    expect(RoleModel::pluck('name')->sort()->values()->all())
        ->toBe(collect(Role::cases())->map->value->sort()->values()->all());
});

it('ensures roles idempotently', function () {
    app(EnsureRoles::class)->handle();
    app(EnsureRoles::class)->handle();

    expect(RoleModel::count())->toBe(count(Role::cases()));
});

it('creates the first admin with a generated password', function () {
    $this->artisan('aksaraloka:install', ['--email' => 'admin@sekolah.test', '--name' => 'Kepala TU'])->assertSuccessful();

    $admin = User::where('email', 'admin@sekolah.test')->firstOrFail();

    expect($admin->name)->toBe('Kepala TU')->and($admin->isAdmin())->toBeTrue();
});

it('creates the admin with the given password', function () {
    $this->artisan('aksaraloka:install', ['--email' => 'a@x.test', '--password' => 'rahasia123'])->assertSuccessful();

    expect(Hash::check('rahasia123', User::firstOrFail()->password))->toBeTrue();
});

it('refuses a duplicate admin email', function () {
    User::factory()->create(['email' => 'a@x.test']);

    $this->artisan('aksaraloka:install', ['--email' => 'a@x.test'])->assertFailed();
});

it('only sets up roles when no email is given', function () {
    $this->artisan('aksaraloka:install')->assertSuccessful();

    expect(User::count())->toBe(0);
});

it('takes the institution name and terminology from config', function () {
    expect(institution_name())->toBe('SMP Contoh')->and(term('teacher'))->toBe('Guru');

    config(['institution.level' => 'campus', 'institution.name' => 'Universitas X']);

    expect(institution_name())->toBe('Universitas X')
        ->and(Terminology::mode())->toBe('campus')
        ->and(term('teacher'))->toBe('Dosen')
        ->and(term('section'))->toBe('Pertemuan')
        ->and(term('section', 'school'))->toBe('Topik');
});

it('falls back to school terms for an unknown level', function () {
    config(['institution.level' => 'lainnya']);

    expect(Terminology::mode())->toBe('school');
});

it('leaves no multi-tenant artifacts in the schema', function () {
    expect(Schema::hasTable('tenants'))->toBeFalse()
        ->and(Schema::hasColumn('users', 'tenant_id'))->toBeFalse()
        ->and(Schema::hasColumn('academic_terms', 'tenant_id'))->toBeFalse()
        ->and(Schema::hasColumn('import_runs', 'tenant_id'))->toBeFalse()
        ->and(Schema::hasColumn('roles', 'tenant_id'))->toBeFalse();
});
