<?php

use App\Actions\Users\ResetUserPassword;
use App\Enums\Role;
use App\Livewire\Admin\Users\Index;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('allows only admin and principal to see the user list', function (Role $role, int $status) {
    $this->actingAs(makeUser($role))->get('/admin/pengguna')->assertStatus($status);
})->with([
    [Role::Admin, 200],
    [Role::Principal, 200],
    [Role::Teacher, 403],
    [Role::Homeroom, 403],
    [Role::Student, 403],
    [Role::Parent, 403],
]);

it('lets every signed-in user view the terms page', function () {
    $this->actingAs(makeUser(Role::Student))->get('/admin/tahun-ajaran')->assertOk();
});

it('lets a principal view but not reset passwords or import', function () {
    $principal = makeUser(Role::Principal);
    $student = makeUser(Role::Student);

    expect($principal->can('viewAny', User::class))->toBeTrue()
        ->and($principal->can('resetPassword', $student))->toBeFalse()
        ->and($principal->can('import', User::class))->toBeFalse();
});

it('lets one account hold several roles', function () {
    $user = makeUser([Role::Teacher, Role::Homeroom]);

    expect($user->hasAppRole(Role::Teacher))->toBeTrue()
        ->and($user->hasAppRole(Role::Homeroom))->toBeTrue()
        ->and($user->hasAppRole(Role::Student))->toBeFalse();
});

it('lets an admin reset a password and shows it once', function () {
    $student = makeUser(Role::Student, ['password' => 'lama12345']);

    $component = Livewire::actingAs(makeUser(Role::Admin))->test(Index::class)->call('resetPassword', $student->id);

    $new = $component->get('revealed')['password'];

    expect(Hash::check($new, $student->fresh()->password))->toBeTrue()
        ->and(Hash::check('lama12345', $student->fresh()->password))->toBeFalse();

    $component->call('dismiss')->assertSet('revealed', null);
});

it('forbids non-admins from resetting passwords', function () {
    $student = makeUser(Role::Student, ['password' => 'lama12345']);

    Livewire::actingAs(makeUser(Role::Teacher))->test(Index::class)->assertForbidden();
    Livewire::actingAs(makeUser(Role::Principal))->test(Index::class)->call('resetPassword', $student->id)->assertForbidden();

    expect(Hash::check('lama12345', $student->fresh()->password))->toBeTrue();
});

it('does not let an admin reset their own password through the admin action', function () {
    $admin = makeUser(Role::Admin);

    expect($admin->can('resetPassword', $admin))->toBeFalse();
});

it('generates a random password when none is given', function () {
    $user = makeUser(Role::Student);

    $a = app(ResetUserPassword::class)->handle($user);
    $b = app(ResetUserPassword::class)->handle($user);

    expect(strlen($a))->toBe(10)->and($a)->not->toBe($b);
});

it('filters and searches the user list', function () {
    makeUser(Role::Teacher, ['name' => 'Bu Guru Mawar']);
    makeUser(Role::Student, ['name' => 'Siswa Melati', 'identity_number' => '555']);

    Livewire::actingAs(makeUser(Role::Admin))->test(Index::class)
        ->set('search', 'Mawar')->assertSee('Bu Guru Mawar')->assertDontSee('Siswa Melati')
        ->set('search', '555')->assertSee('Siswa Melati')->assertDontSee('Bu Guru Mawar')
        ->set('search', '')->set('role', 'teacher')->assertSee('Bu Guru Mawar')->assertDontSee('Siswa Melati');
});

it('treats LIKE wildcards in search literally', function () {
    makeUser(Role::Student, ['name' => 'Budi']);

    Livewire::actingAs(makeUser(Role::Admin))->test(Index::class)->set('search', '%')->assertDontSee('Budi');
});
