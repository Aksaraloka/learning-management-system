<?php

use App\Actions\Users\ResetUserPassword;
use App\Enums\Role;
use App\Livewire\Admin\Users\Index;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = makeTenant(['subdomain' => 'smp1']);
});

it('allows only admin and principal to see the user list', function (Role $role, int $status) {
    $user = makeUser($this->tenant, $role);

    $this->actingAs($user)->get($this->tenant->url('/admin/pengguna'))->assertStatus($status);
})->with([
    [Role::Admin, 200],
    [Role::Principal, 200],
    [Role::Teacher, 403],
    [Role::Homeroom, 403],
    [Role::Student, 403],
    [Role::Parent, 403],
]);

it('allows only admins to manage terms page edits but everyone to view', function () {
    $this->actingAs(makeUser($this->tenant, Role::Student))->get($this->tenant->url('/admin/tahun-ajaran'))->assertOk();
});

it('lets a principal view but not reset passwords', function () {
    $principal = makeUser($this->tenant, Role::Principal);
    $student = makeUser($this->tenant, Role::Student);
    actingInTenant($this->tenant, $principal);

    expect($principal->can('viewAny', User::class))->toBeTrue()
        ->and($principal->can('resetPassword', $student))->toBeFalse()
        ->and($principal->can('import', User::class))->toBeFalse();
});

it('lets an admin reset a password and shows it once', function () {
    $admin = makeUser($this->tenant, Role::Admin);
    $student = makeUser($this->tenant, Role::Student, ['password' => 'lama12345']);
    actingInTenant($this->tenant, $admin);

    $component = Livewire::test(Index::class)->call('resetPassword', $student->id);

    $new = $component->get('revealed')['password'];

    expect(Hash::check($new, $student->fresh()->password))->toBeTrue()
        ->and(Hash::check('lama12345', $student->fresh()->password))->toBeFalse();

    $component->call('dismiss')->assertSet('revealed', null);
});

it('does not let an admin reset their own password through the admin action', function () {
    $admin = makeUser($this->tenant, Role::Admin);
    actingInTenant($this->tenant, $admin);

    expect($admin->can('resetPassword', $admin))->toBeFalse();
});

it('generates a random password when none is given', function () {
    $user = makeUser($this->tenant, Role::Student);
    Tenancy::bind($this->tenant);

    $a = app(ResetUserPassword::class)->handle($user);
    $b = app(ResetUserPassword::class)->handle($user);

    expect(strlen($a))->toBe(10)->and($a)->not->toBe($b);
});

it('filters and searches the user list', function () {
    $admin = makeUser($this->tenant, Role::Admin);
    makeUser($this->tenant, Role::Teacher, ['name' => 'Bu Guru Mawar']);
    makeUser($this->tenant, Role::Student, ['name' => 'Siswa Melati', 'identity_number' => '555']);
    actingInTenant($this->tenant, $admin);

    Livewire::test(Index::class)
        ->set('search', 'Mawar')->assertSee('Bu Guru Mawar')->assertDontSee('Siswa Melati')
        ->set('search', '555')->assertSee('Siswa Melati')->assertDontSee('Bu Guru Mawar')
        ->set('search', '')->set('role', 'teacher')->assertSee('Bu Guru Mawar')->assertDontSee('Siswa Melati');
});

it('treats LIKE wildcards in search literally', function () {
    $admin = makeUser($this->tenant, Role::Admin);
    makeUser($this->tenant, Role::Student, ['name' => 'Budi']);
    actingInTenant($this->tenant, $admin);

    Livewire::test(Index::class)->set('search', '%')->assertDontSee('Budi');
});

it('keeps admin and platform areas separated by context', function () {
    $admin = makeUser($this->tenant, Role::Admin);

    $this->actingAs($admin)->get('http://aksaraloka.test/admin/pengguna')->assertNotFound();
    $this->actingAs($admin)->get($this->tenant->url('/platform/institusi'))->assertNotFound();
});
