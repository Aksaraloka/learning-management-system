<?php

use App\Enums\Role;
use App\Livewire\Profile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake(config('courses.disk'));
    $this->user = makeUser(Role::Student, ['name' => 'Ani', 'email' => 'ani@kampus.test', 'identity_number' => '2260001']);
});

it('shows editable profile fields', function () {
    $this->actingAs($this->user)->get(route('profile.edit'))->assertOk()
        ->assertSee('Nomor telepon')->assertSee('Tentang saya')->assertSee('Foto profil')->assertSee('2260001');
});

it('updates name, email, phone and bio', function () {
    Livewire::actingAs($this->user)->test(Profile::class)
        ->set('name', 'Ani Lestari')->set('email', 'ani.baru@kampus.test')->set('phone', '+62 812-3456-7890')->set('bio', 'Mahasiswa Informatika')
        ->call('saveInfo')->assertHasNoErrors();

    $fresh = $this->user->fresh();

    expect($fresh->name)->toBe('Ani Lestari')->and($fresh->email)->toBe('ani.baru@kampus.test')
        ->and($fresh->phone)->toBe('+62 812-3456-7890')->and($fresh->bio)->toBe('Mahasiswa Informatika');
});

it('validates the profile fields', function () {
    makeUser(Role::Teacher, ['email' => 'dipakai@kampus.test']);

    Livewire::actingAs($this->user)->test(Profile::class)
        ->set('name', '')->set('email', 'bukan-email')->set('phone', 'abc')->set('bio', str_repeat('x', 501))
        ->call('saveInfo')->assertHasErrors(['name', 'email', 'phone', 'bio']);

    Livewire::actingAs($this->user)->test(Profile::class)->set('email', 'dipakai@kampus.test')->call('saveInfo')->assertHasErrors(['email']);
});

it('keeps the own email without a uniqueness error', function () {
    Livewire::actingAs($this->user)->test(Profile::class)->set('name', 'Ani B')->call('saveInfo')->assertHasNoErrors();
});

it('lets an account with an identity number clear the email, but not one without', function () {
    Livewire::actingAs($this->user)->test(Profile::class)->set('email', '')->call('saveInfo')->assertHasNoErrors();
    expect($this->user->fresh()->email)->toBeNull();

    $noId = makeUser(Role::Teacher, ['email' => 'dosen@kampus.test', 'identity_number' => null]);

    Livewire::actingAs($noId)->test(Profile::class)->set('email', '')->call('saveInfo')->assertHasErrors(['email']);
});

it('uploads, serves, replaces and removes a profile photo', function () {
    $page = fn () => Livewire::actingAs($this->user->fresh())->test(Profile::class);

    $page()->set('avatar', UploadedFile::fake()->image('a.png'))->call('saveInfo')->assertHasNoErrors();
    $first = $this->user->fresh();

    expect($first->hasAvatar())->toBeTrue()->and($first->avatarUrl())->toContain("/pengguna/{$first->id}/foto");
    Storage::disk(config('courses.disk'))->assertExists($first->avatar_path);
    $this->actingAs($first)->get(route('users.avatar', $first))->assertOk();

    $page()->set('avatar', UploadedFile::fake()->image('b.jpg'))->call('saveInfo')->assertHasNoErrors();
    Storage::disk(config('courses.disk'))->assertMissing($first->avatar_path);
    $second = $this->user->fresh();
    Storage::disk(config('courses.disk'))->assertExists($second->avatar_path);

    $page()->set('removeAvatar', true)->call('saveInfo')->assertHasNoErrors();
    expect($this->user->fresh()->hasAvatar())->toBeFalse();
    Storage::disk(config('courses.disk'))->assertMissing($second->avatar_path);
});

it('keeps the photo when other fields are saved', function () {
    Livewire::actingAs($this->user)->test(Profile::class)->set('avatar', UploadedFile::fake()->image('a.png'))->call('saveInfo');
    $path = $this->user->fresh()->avatar_path;

    Livewire::actingAs($this->user->fresh())->test(Profile::class)->set('bio', 'Baru')->call('saveInfo')->assertHasNoErrors();

    expect($this->user->fresh()->avatar_path)->toBe($path);
});

it('rejects non-image photos', function () {
    Livewire::actingAs($this->user)->test(Profile::class)
        ->set('avatar', UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'))->call('saveInfo')->assertHasErrors(['avatar']);
});

it('shows the photo in the header and requires login to fetch it', function () {
    Livewire::actingAs($this->user)->test(Profile::class)->set('avatar', UploadedFile::fake()->image('a.png'))->call('saveInfo');
    $user = $this->user->fresh();

    $this->actingAs($user)->get(route('home'))->assertSee("/pengguna/{$user->id}/foto", false);

    auth()->logout();
    $this->get(route('users.avatar', $user))->assertRedirect(route('login'));
});

it('returns 404 for a user without a photo', function () {
    $this->actingAs($this->user)->get(route('users.avatar', $this->user))->assertNotFound();
});

it('still changes the password with the current one', function () {
    $this->user->update(['password' => 'lama-sandi-1']);

    Livewire::actingAs($this->user)->test(Profile::class)
        ->set('current_password', 'lama-sandi-1')->set('password', 'baru-sandi-2')->set('password_confirmation', 'baru-sandi-2')
        ->call('savePassword')->assertHasNoErrors();

    expect(Hash::check('baru-sandi-2', $this->user->fresh()->password))->toBeTrue();
});
