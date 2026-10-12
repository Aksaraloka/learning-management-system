<?php

use App\Enums\Role;
use App\Livewire\Admin\Terms;
use App\Livewire\Admin\Users;
use App\Models\AcademicTerm;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->a = makeTenant(['subdomain' => 'sekolah-a']);
    $this->b = makeTenant(['subdomain' => 'sekolah-b']);

    $this->adminA = makeUser($this->a, Role::Admin, ['name' => 'Admin A']);
    $this->adminB = makeUser($this->b, Role::Admin, ['name' => 'Admin B']);
    $this->studentB = makeUser($this->b, Role::Student, ['name' => 'Siswa B']);

    Tenancy::run($this->a, fn () => AcademicTerm::factory()->create(['name' => 'Term A']));
    Tenancy::run($this->b, fn () => AcademicTerm::factory()->create(['name' => 'Term B']));
});

it('only returns the active tenant\'s rows', function () {
    Tenancy::bind($this->a);

    expect(User::pluck('name')->all())->toBe(['Admin A'])
        ->and(AcademicTerm::pluck('name')->all())->toBe(['Term A']);

    Tenancy::bind($this->b);

    expect(User::pluck('name')->sort()->values()->all())->toBe(['Admin B', 'Siswa B'])
        ->and(AcademicTerm::pluck('name')->all())->toBe(['Term B']);
});

it('cannot find another tenant\'s record by id', function () {
    Tenancy::bind($this->a);

    expect(fn () => User::findOrFail($this->studentB->id))->toThrow(ModelNotFoundException::class)
        ->and(User::find($this->studentB->id))->toBeNull()
        ->and(User::where('id', $this->studentB->id)->exists())->toBeFalse();
});

it('fills tenant_id automatically when creating', function () {
    Tenancy::bind($this->a);

    $term = AcademicTerm::create(['name' => 'Baru', 'starts_at' => '2027-01-01', 'ends_at' => '2027-06-01']);

    expect($term->tenant_id)->toBe($this->a->id);
});

it('allows the same NIS in two different tenants', function () {
    $nis = '2026001';

    makeUser($this->a, Role::Student, ['identity_number' => $nis]);
    makeUser($this->b, Role::Student, ['identity_number' => $nis]);

    expect(User::withoutGlobalScopes()->where('identity_number', $nis)->count())->toBe(2);
});

it('does not let an admin reset a password of a user in another tenant', function () {
    actingInTenant($this->a, $this->adminA);

    $before = $this->studentB->fresh()->password;

    Livewire::test(Users\Index::class)->call('resetPassword', $this->studentB->id)->assertNotFound();

    expect($this->studentB->fresh()->password)->toBe($before);
});

it('does not let an admin edit or lock another tenant\'s term', function () {
    $termB = Tenancy::run($this->b, fn () => AcademicTerm::firstOrFail());

    actingInTenant($this->a, $this->adminA);

    Livewire::test(Terms\Index::class)->call('toggleLock', $termB->id)->assertNotFound();
    Livewire::test(Terms\Index::class)->call('delete', $termB->id)->assertNotFound();

    expect($termB->fresh()->is_locked)->toBeFalse()->and(AcademicTerm::withoutGlobalScopes()->find($termB->id))->not->toBeNull();
});

it('lists only own tenant users in the admin page', function () {
    actingInTenant($this->b, $this->adminB);

    Livewire::test(Users\Index::class)->assertSee('Siswa B')->assertDontSee('Admin A');
});

it('logs out a session that belongs to another tenant', function () {
    $this->actingAs($this->adminA)
        ->get($this->b->url('/profil'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('keeps roles separated per tenant', function () {
    $multi = makeUser($this->a, [Role::Teacher, Role::Homeroom]);

    Tenancy::bind($this->a);
    expect($multi->fresh()->hasAppRole(Role::Teacher))->toBeTrue()
        ->and($multi->fresh()->hasAppRole(Role::Homeroom))->toBeTrue();

    // Konteks tenant lain: peran A tidak berlaku.
    Tenancy::bind($this->b);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    expect(User::withoutGlobalScopes()->find($multi->id)->hasAppRole(Role::Teacher))->toBeFalse();
});
