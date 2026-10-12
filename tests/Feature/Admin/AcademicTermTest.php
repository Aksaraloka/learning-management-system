<?php

use App\Enums\Role;
use App\Livewire\Admin\Terms\Index;
use App\Models\AcademicTerm;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = makeTenant(['subdomain' => 'smp1']);
    $this->admin = makeUser($this->tenant, Role::Admin);
});

it('lets an admin create a term for their tenant', function () {
    actingInTenant($this->tenant, $this->admin);

    Livewire::test(Index::class)
        ->set('name', '2026/2027 Ganjil')->set('starts_at', '2026-07-13')->set('ends_at', '2026-12-19')
        ->call('save')->assertHasNoErrors();

    $term = AcademicTerm::firstOrFail();

    expect($term->tenant_id)->toBe($this->tenant->id)->and($term->name)->toBe('2026/2027 Ganjil');
});

it('validates the term period', function () {
    actingInTenant($this->tenant, $this->admin);

    Livewire::test(Index::class)
        ->set('name', 'X')->set('starts_at', '2026-12-19')->set('ends_at', '2026-07-13')
        ->call('save')->assertHasErrors('ends_at');

    Livewire::test(Index::class)->call('save')->assertHasErrors(['name', 'starts_at', 'ends_at']);
});

it('edits a term', function () {
    $term = AcademicTerm::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Lama']);
    actingInTenant($this->tenant, $this->admin);

    Livewire::test(Index::class)->call('edit', $term->id)->assertSet('name', 'Lama')
        ->set('name', 'Baru')->call('save')->assertHasNoErrors();

    expect($term->fresh()->name)->toBe('Baru');
});

it('locks a term and then forbids edits and deletion', function () {
    $term = AcademicTerm::factory()->create(['tenant_id' => $this->tenant->id]);
    actingInTenant($this->tenant, $this->admin);

    Livewire::test(Index::class)->call('toggleLock', $term->id);
    expect($term->fresh()->is_locked)->toBeTrue();

    Livewire::test(Index::class)->call('edit', $term->id)->assertForbidden();
    Livewire::test(Index::class)->call('delete', $term->id)->assertForbidden();

    Livewire::test(Index::class)->call('toggleLock', $term->id);
    expect($term->fresh()->is_locked)->toBeFalse();
});

it('forbids non-admins from changing terms', function (Role $role) {
    $term = AcademicTerm::factory()->create(['tenant_id' => $this->tenant->id]);
    actingInTenant($this->tenant, makeUser($this->tenant, $role));

    Livewire::test(Index::class)->set('name', 'X')->set('starts_at', '2026-01-01')->set('ends_at', '2026-06-01')->call('save')->assertForbidden();
    Livewire::test(Index::class)->call('toggleLock', $term->id)->assertForbidden();

    expect(AcademicTerm::count())->toBe(1);
})->with([Role::Teacher, Role::Student, Role::Principal, Role::Homeroom]);

it('soft deletes an open term', function () {
    $term = AcademicTerm::factory()->create(['tenant_id' => $this->tenant->id]);
    actingInTenant($this->tenant, $this->admin);

    Livewire::test(Index::class)->call('delete', $term->id);

    expect(AcademicTerm::count())->toBe(0)->and(AcademicTerm::withTrashed()->count())->toBe(1);
});
