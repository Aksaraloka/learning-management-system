<?php

use App\Enums\Role;
use App\Livewire\Auth\Login;
use App\Livewire\Platform\Tenants\Index;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Livewire;

function superAdmin(): User
{
    return User::factory()->superAdmin()->create();
}

it('redirects the super admin from home to the tenant panel', function () {
    $this->actingAs(superAdmin())->get('http://aksaraloka.test/')->assertRedirect(route('platform.tenants.index'));
});

it('creates a tenant with default roles and an admin account', function () {
    $this->actingAs(superAdmin());
    Tenancy::forget();

    Livewire::test(Index::class)
        ->set('name', 'SMA Negeri 3')->set('subdomain', 'sman3')->set('level', 'school')
        ->set('admin_name', 'Pak Admin')->set('admin_email', 'admin@sman3.test')
        ->call('create')->assertHasNoErrors();

    $tenant = Tenant::where('subdomain', 'sman3')->firstOrFail();
    $admin = User::withoutGlobalScopes()->where('email', 'admin@sman3.test')->firstOrFail();

    Tenancy::bind($tenant);

    expect($admin->tenant_id)->toBe($tenant->id)
        ->and($admin->fresh()->hasAppRole(Role::Admin))->toBeTrue()
        ->and(Spatie\Permission\Models\Role::where('tenant_id', $tenant->id)->count())->toBe(count(Role::tenantRoles()));
});

it('lets the new tenant admin log in on the tenant subdomain', function () {
    $this->actingAs(superAdmin());
    Tenancy::forget();

    $component = Livewire::test(Index::class)
        ->set('name', 'SMK 1')->set('subdomain', 'smk1')->set('level', 'school')
        ->set('admin_name', 'Admin SMK')->set('admin_email', 'admin@smk1.test')->call('create');

    $password = $component->get('created')['password'];

    Auth::logout();
    Tenancy::bind(Tenant::where('subdomain', 'smk1')->firstOrFail());

    Livewire::test(Login::class)->set('identifier', 'admin@smk1.test')->set('password', $password)->call('login')->assertHasNoErrors();
});

it('validates the subdomain', function (string $subdomain) {
    $this->actingAs(superAdmin());
    makeTenant(['subdomain' => 'dipakai']);
    Tenancy::forget();

    Livewire::test(Index::class)
        ->set('name', 'X')->set('subdomain', $subdomain)->set('admin_name', 'A')->set('admin_email', 'a@x.test')
        ->call('create')->assertHasErrors('subdomain');

    expect(Tenant::where('subdomain', $subdomain)->exists())->toBe($subdomain === 'dipakai');
})->with(['dipakai', 'www', 'admin', 'a', 'Pakai Spasi', 'titik.dalam', '-awal', 'akhir-']);

it('rejects an admin email that already exists', function () {
    $this->actingAs(superAdmin());
    $existing = makeTenant(['subdomain' => 'lama']);
    makeUser($existing, Role::Admin, ['email' => 'dipakai@x.test']);
    Tenancy::forget();

    Livewire::test(Index::class)
        ->set('name', 'X')->set('subdomain', 'baru')->set('admin_name', 'A')->set('admin_email', 'dipakai@x.test')
        ->call('create')->assertHasErrors('admin_email');
});

it('suspends and reactivates a tenant', function () {
    $this->actingAs(superAdmin());
    $tenant = makeTenant(['subdomain' => 'target']);
    Tenancy::forget();

    Livewire::test(Index::class)->call('toggleStatus', $tenant->id);
    expect($tenant->fresh()->status)->toBe('suspended');

    $this->get($tenant->url('/login'))->assertForbidden();

    Livewire::test(Index::class)->call('toggleStatus', $tenant->id);
    expect($tenant->fresh()->isActive())->toBeTrue();
});

it('forbids tenant users from the platform panel', function (Role $role) {
    $tenant = makeTenant(['subdomain' => 'smp1']);
    $user = makeUser($tenant, $role);

    $this->actingAs($user)->get('http://aksaraloka.test/platform/institusi')->assertRedirect(route('login'));
})->with([Role::Admin, Role::Teacher, Role::Student]);

it('forbids policy checks for non-super-admins', function () {
    $tenant = makeTenant(['subdomain' => 'smp1']);
    $admin = makeUser($tenant, Role::Admin);
    $this->actingAs($admin);
    Tenancy::forget();

    Livewire::test(Index::class)->assertForbidden();
});
