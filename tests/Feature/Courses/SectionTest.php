<?php

use App\Enums\Role;
use App\Livewire\Courses\Show;
use App\Models\Material;
use App\Models\Section;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');

    $this->teacher = makeUser(Role::Teacher);
    $this->course = makeCourse($this->teacher);
});

it('adds sections with automatic numbering', function () {
    $component = Livewire::actingAs($this->teacher)->test(Show::class, ['course' => $this->course]);

    $component->call('addSection')->set('newSectionTitle', 'Pecahan')->call('addSection')->call('addSection');

    expect($this->course->sections->pluck('title')->all())->toBe(['Pertemuan 1', 'Pecahan', 'Pertemuan 3'])
        ->and($this->course->sections->pluck('position')->all())->toBe([1, 2, 3]);
});

it('numbers meetings and caps them at 16', function () {
    $component = Livewire::actingAs($this->teacher)->test(Show::class, ['course' => $this->course]);

    foreach (range(1, 16) as $_) {
        $component->call('addSection');
    }

    expect($this->course->sections()->first()->title)->toBe('Pertemuan 1')
        ->and($this->course->sections()->count())->toBe(16);

    $component->call('addSection')->assertHasErrors('title');

    expect($this->course->sections()->count())->toBe(16);
});

it('lets a lecturer reuse a freed meeting number after deleting one', function () {
    $component = Livewire::actingAs($this->teacher)->test(Show::class, ['course' => $this->course]);

    foreach (range(1, 16) as $_) {
        $component->call('addSection');
    }

    $component->call('deleteSection', $this->course->sections()->first()->id)->call('addSection')->assertHasNoErrors();

    expect($this->course->sections()->count())->toBe(16);
});

it('renames a section', function () {
    $section = makeSection($this->course, 'Lama');

    Livewire::actingAs($this->teacher)->test(Show::class, ['course' => $this->course])
        ->call('startRename', $section->id)->assertSet('renamingTitle', 'Lama')
        ->set('renamingTitle', 'Baru')->call('saveRename')->assertSet('renamingId', null);

    expect($section->fresh()->title)->toBe('Baru');
});

it('refuses an empty section title when renaming', function () {
    $section = makeSection($this->course, 'Lama');

    Livewire::actingAs($this->teacher)->test(Show::class, ['course' => $this->course])
        ->call('startRename', $section->id)->set('renamingTitle', '  ')->call('saveRename')->assertHasErrors('renamingTitle');

    expect($section->fresh()->title)->toBe('Lama');
});

it('reorders sections and ignores ids from another course', function () {
    $a = makeSection($this->course, 'A');
    $b = makeSection($this->course, 'B');
    $c = makeSection($this->course, 'C');
    $foreign = makeSection(makeCourse(), 'Asing');

    Livewire::actingAs($this->teacher)->test(Show::class, ['course' => $this->course])
        ->call('reorderSections', [$c->id, $foreign->id, $a->id]);

    expect($this->course->sections()->pluck('title')->all())->toBe(['C', 'A', 'B'])
        ->and($foreign->fresh()->position)->toBe(1);
});

it('reorders materials inside a section', function () {
    $section = makeSection($this->course);
    $m1 = makeMaterial($section, ['title' => 'Satu']);
    $m2 = makeMaterial($section, ['title' => 'Dua']);

    Livewire::actingAs($this->teacher)->test(Show::class, ['course' => $this->course])
        ->call('reorderMaterials', $section->id, [$m2->id, $m1->id]);

    expect($section->materials()->pluck('title')->all())->toBe(['Dua', 'Satu']);
});

it('deletes a section with its materials and files, then renumbers', function () {
    $keep = makeSection($this->course, 'Tetap');
    $drop = makeSection($this->course, 'Hapus');
    $last = makeSection($this->course, 'Akhir');

    Storage::disk('local')->put('courses/1/a.pdf', 'x');
    makeMaterial($drop, ['type' => 'file', 'file_path' => 'courses/1/a.pdf', 'file_name' => 'a.pdf', 'mime_type' => 'application/pdf']);
    makeMaterial($drop);

    Livewire::actingAs($this->teacher)->test(Show::class, ['course' => $this->course])->call('deleteSection', $drop->id);

    Storage::disk('local')->assertMissing('courses/1/a.pdf');

    expect(Section::find($drop->id))->toBeNull()
        ->and(Material::count())->toBe(0)
        ->and($this->course->sections()->pluck('position', 'title')->all())->toBe(['Tetap' => 1, 'Akhir' => 2])
        ->and($keep->fresh()->position)->toBe(1)
        ->and($last->fresh()->position)->toBe(2);
});

it('returns 404 for sections of another course', function () {
    $foreign = makeSection(makeCourse(), 'Asing');

    // Respons 404/403 mengakhiri komponen, jadi tiap panggilan memakai komponen baru.
    Livewire::actingAs($this->teacher)->test(Show::class, ['course' => $this->course])->call('startRename', $foreign->id)->assertNotFound();
    Livewire::actingAs($this->teacher)->test(Show::class, ['course' => $this->course])->call('deleteSection', $foreign->id)->assertNotFound();

    expect(Section::find($foreign->id))->not->toBeNull();
});

it('forbids students from managing sections', function () {
    $student = makeUser(Role::Student);
    enroll($this->course, $student);
    $section = makeSection($this->course);

    $show = fn () => Livewire::actingAs($student)->test(Show::class, ['course' => $this->course]);

    $show()->call('addSection')->assertForbidden();
    $show()->call('startRename', $section->id)->assertForbidden();
    $show()->call('deleteSection', $section->id)->assertForbidden();
    $show()->call('reorderSections', [$section->id])->assertForbidden();

    expect(Section::count())->toBe(1)->and($section->fresh()->title)->toBe('Bab 1');
});

it('forbids an unrelated teacher from opening the course at all', function () {
    Livewire::actingAs(makeUser(Role::Teacher))->test(Show::class, ['course' => $this->course])->assertForbidden();
});

it('does not show management controls to students', function () {
    $student = makeUser(Role::Student);
    enroll($this->course, $student);
    makeSection($this->course, 'Bab Rahasia');

    Livewire::actingAs($student)->test(Show::class, ['course' => $this->course])
        ->assertSee('Bab Rahasia')->assertDontSee('addSection', false)->assertDontSee('startRename', false)->assertDontSee('cursor-grab', false);
});

it('renders a fold toggle per section and a fold-all control on the course page', function () {
    $teacher = makeUser(Role::Teacher);
    $course = makeCourse($teacher);
    $one = makeSection($course, 'Pertemuan Satu');
    makeSection($course, 'Pertemuan Dua');
    makeMaterial($one);

    $html = $this->actingAs($teacher)->get(route('courses.show', $course))->assertOk()
        ->assertSee('Ciutkan semua')->assertSee('x-data="sectionFold', false)->assertSee('data-fold-id="'.$one->id.'"', false)
        ->assertSee('Lipat atau bentangkan: Pertemuan Satu')->assertSee('1 isi')->getContent();

    expect(substr_count($html, 'data-fold-body'))->toBeGreaterThanOrEqual(2);

    // Mahasiswa juga mendapat tombol lipat (hanya tampilan, bukan hak akses).
    $student = makeUser(Role::Student);
    enroll($course, $student);
    $this->actingAs($student)->get(route('courses.show', $course))->assertOk()->assertSee('Lipat atau bentangkan: Pertemuan Satu');
});

it('keeps the fold-all control away from courses without sections', function () {
    $teacher = makeUser(Role::Teacher);
    $course = makeCourse($teacher);

    $this->actingAs($teacher)->get(route('courses.show', $course))->assertOk()->assertDontSee('Ciutkan semua');
});
