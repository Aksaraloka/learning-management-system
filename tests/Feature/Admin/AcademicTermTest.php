<?php

use App\Enums\Role;
use App\Livewire\Admin\Terms\Index;
use App\Models\AcademicTerm;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = makeUser(Role::Admin);
});

it('lets an admin create a term', function () {
    Livewire::actingAs($this->admin)->test(Index::class)
        ->set('name', '2026/2027 Ganjil')->set('starts_at', '2026-07-13')->set('ends_at', '2026-12-19')
        ->call('save')->assertHasNoErrors();

    expect(AcademicTerm::firstOrFail()->name)->toBe('2026/2027 Ganjil');
});

it('validates the term period', function () {
    Livewire::actingAs($this->admin)->test(Index::class)
        ->set('name', 'X')->set('starts_at', '2026-12-19')->set('ends_at', '2026-07-13')
        ->call('save')->assertHasErrors('ends_at');

    Livewire::actingAs($this->admin)->test(Index::class)->call('save')->assertHasErrors(['name', 'starts_at', 'ends_at']);
});

it('edits a term', function () {
    $term = AcademicTerm::factory()->create(['name' => 'Lama']);

    Livewire::actingAs($this->admin)->test(Index::class)->call('edit', $term->id)->assertSet('name', 'Lama')
        ->set('name', 'Baru')->call('save')->assertHasNoErrors();

    expect($term->fresh()->name)->toBe('Baru');
});

it('locks a term and then forbids edits and deletion', function () {
    $term = AcademicTerm::factory()->create();

    $component = Livewire::actingAs($this->admin)->test(Index::class)->call('toggleLock', $term->id);
    expect($term->fresh()->is_locked)->toBeTrue();

    Livewire::actingAs($this->admin)->test(Index::class)->call('edit', $term->id)->assertForbidden();
    Livewire::actingAs($this->admin)->test(Index::class)->call('delete', $term->id)->assertForbidden();

    $component->call('toggleLock', $term->id);
    expect($term->fresh()->is_locked)->toBeFalse();
});

it('forbids non-admins from changing terms', function (Role $role) {
    $term = AcademicTerm::factory()->create();
    $user = makeUser($role);

    Livewire::actingAs($user)->test(Index::class)
        ->set('name', 'X')->set('starts_at', '2026-01-01')->set('ends_at', '2026-06-01')->call('save')->assertForbidden();
    Livewire::actingAs($user)->test(Index::class)->call('toggleLock', $term->id)->assertForbidden();

    expect(AcademicTerm::count())->toBe(1)->and($term->fresh()->is_locked)->toBeFalse();
})->with([Role::Teacher, Role::Student, Role::Principal, Role::Homeroom]);

it('soft deletes an open term', function () {
    $term = AcademicTerm::factory()->create();

    Livewire::actingAs($this->admin)->test(Index::class)->call('delete', $term->id);

    expect(AcademicTerm::count())->toBe(0)->and(AcademicTerm::withTrashed()->count())->toBe(1);
});
