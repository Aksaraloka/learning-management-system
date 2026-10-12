<?php

use App\Enums\MaterialType;
use App\Enums\Role;
use App\Livewire\Courses\Show;
use App\Livewire\Materials\Form;
use App\Livewire\Materials\Show as MaterialShow;
use App\Models\Material;
use App\Support\Upload;
use App\Support\VideoEmbed;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');

    $this->teacher = makeUser(Role::Teacher);
    $this->course = makeCourse($this->teacher);
    $this->section = makeSection($this->course);
});

function materialForm($test, array $props = []): Testable
{
    return Livewire::actingAs($test->teacher)->test(Form::class, ['course' => $test->course] + $props);
}

it('creates a text material and sanitizes the HTML', function () {
    materialForm($this)
        ->set('section_id', (string) $this->section->id)->set('type', 'text')->set('title', 'Pengantar')
        ->set('body', '<p>Halo <strong>dunia</strong></p><script>alert(1)</script><a href="javascript:alert(2)" onclick="x()">klik</a><img src=x onerror=alert(3)>')
        ->call('save')->assertHasNoErrors();

    $material = Material::firstOrFail();

    expect($material->type)->toBe(MaterialType::Text)
        ->and($material->body)->toContain('<strong>dunia</strong>')
        ->and($material->body)->not->toContain('script')
        ->and($material->body)->not->toContain('javascript')
        ->and($material->body)->not->toContain('onclick')
        ->and($material->body)->not->toContain('<img')
        ->and($material->position)->toBe(1);
});

it('keeps math delimiters for KaTeX', function () {
    materialForm($this)
        ->set('section_id', (string) $this->section->id)->set('title', 'Rumus')
        ->set('body', '<p>Luas: $L = \pi r^2$</p>')->call('save');

    expect(Material::firstOrFail()->body)->toContain('$L = \pi r^2$');
});

it('requires non-empty text content', function () {
    materialForm($this)->set('section_id', (string) $this->section->id)->set('title', 'Kosong')
        ->set('body', '<p> </p>')->call('save')->assertHasErrors('body');
});

it('validates link and video URLs', function (string $type, string $url, bool $valid) {
    $component = materialForm($this)
        ->set('section_id', (string) $this->section->id)->set('type', $type)->set('title', 'Tautan')->set('url', $url)->call('save');

    $valid ? $component->assertHasNoErrors() : $component->assertHasErrors('url');

    expect(Material::count())->toBe($valid ? 1 : 0);
})->with([
    ['link', 'https://contoh.id/bacaan', true],
    ['video', 'https://www.youtube.com/watch?v=aqz-KE-bpKQ', true],
    ['link', 'javascript:alert(1)', false],
    ['link', 'ftp://contoh.id/x', false],
    ['link', 'bukan url', false],
    ['video', '', false],
]);

it('uploads a PDF and stores it privately with a random name', function () {
    materialForm($this)
        ->set('section_id', (string) $this->section->id)->set('type', 'file')->set('title', 'Slide')
        ->set('upload', UploadedFile::fake()->createWithContent('Slide Bab 1.pdf', pdfBytes()))
        ->call('save')->assertHasNoErrors();

    $material = Material::firstOrFail();

    expect($material->type)->toBe(MaterialType::File)
        ->and($material->file_name)->toBe('Slide Bab 1.pdf')
        ->and($material->mime_type)->toBe('application/pdf')
        ->and($material->file_size)->toBe(strlen(pdfBytes()))
        ->and($material->file_path)->toStartWith("courses/{$this->course->id}/")
        ->and($material->file_path)->not->toContain('Slide');

    Storage::disk('local')->assertExists($material->file_path);
});

it('requires a file for a new file material', function () {
    materialForm($this)->set('section_id', (string) $this->section->id)->set('type', 'file')->set('title', 'Slide')
        ->call('save')->assertHasErrors('upload');
});

it('rejects dangerous or oversized uploads', function () {
    config(['courses.max_upload_kb' => 100]);

    $component = materialForm($this)->set('section_id', (string) $this->section->id)->set('type', 'file')->set('title', 'Berkas');

    $component->set('upload', UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;'))->call('save')->assertHasErrors('upload');
    $component->set('upload', UploadedFile::fake()->createWithContent('virus.exe', 'MZ'.str_repeat("\0", 64)))->call('save')->assertHasErrors('upload');
    $component->set('upload', UploadedFile::fake()->createWithContent('lolos.pdf.php', pdfBytes()))->call('save')->assertHasErrors('upload');
    $component->set('upload', UploadedFile::fake()->createWithContent('besar.pdf', pdfBytes().str_repeat('0', 200 * 1024)))->call('save')->assertHasErrors('upload');

    expect(Material::count())->toBe(0);
});

it('replaces a file and deletes the old one', function () {
    $component = materialForm($this)
        ->set('section_id', (string) $this->section->id)->set('type', 'file')->set('title', 'Slide')
        ->set('upload', UploadedFile::fake()->createWithContent('lama.pdf', pdfBytes()))->call('save');

    $material = Material::firstOrFail();
    $old = $material->file_path;

    materialForm($this, ['material' => $material])
        ->assertSet('title', 'Slide')
        ->set('upload', UploadedFile::fake()->createWithContent('baru.pdf', pdfBytes()))->call('save')->assertHasNoErrors();

    $material->refresh();

    expect($material->file_name)->toBe('baru.pdf')->and($material->file_path)->not->toBe($old);
    Storage::disk('local')->assertMissing($old);
    Storage::disk('local')->assertExists($material->file_path);
});

it('keeps the existing file when editing without a new upload', function () {
    materialForm($this)->set('section_id', (string) $this->section->id)->set('type', 'file')->set('title', 'Slide')
        ->set('upload', UploadedFile::fake()->createWithContent('a.pdf', pdfBytes()))->call('save');

    $material = Material::firstOrFail();
    $path = $material->file_path;

    materialForm($this, ['material' => $material])->set('title', 'Slide v2')->call('save')->assertHasNoErrors();

    expect($material->fresh()->title)->toBe('Slide v2')->and($material->fresh()->file_path)->toBe($path);
    Storage::disk('local')->assertExists($path);
});

it('drops the file when a material changes to another type', function () {
    materialForm($this)->set('section_id', (string) $this->section->id)->set('type', 'file')->set('title', 'Slide')
        ->set('upload', UploadedFile::fake()->createWithContent('a.pdf', pdfBytes()))->call('save');

    $material = Material::firstOrFail();
    $path = $material->file_path;

    materialForm($this, ['material' => $material])->set('type', 'link')->set('url', 'https://contoh.id')->call('save')->assertHasNoErrors();

    expect($material->fresh()->file_path)->toBeNull()->and($material->fresh()->type)->toBe(MaterialType::Link);
    Storage::disk('local')->assertMissing($path);
});

it('deletes a material and its file', function () {
    Storage::disk('local')->put('courses/1/x.pdf', 'x');
    $material = makeMaterial($this->section, ['type' => 'file', 'file_path' => 'courses/1/x.pdf', 'file_name' => 'x.pdf', 'mime_type' => 'application/pdf']);

    Livewire::actingAs($this->teacher)->test(Show::class, ['course' => $this->course])->call('deleteMaterial', $material->id);

    expect(Material::count())->toBe(0);
    Storage::disk('local')->assertMissing('courses/1/x.pdf');
});

it('cannot attach a material to a section of another course', function () {
    $foreign = makeSection(makeCourse(), 'Asing');

    materialForm($this)->set('section_id', (string) $foreign->id)->set('title', 'X')->set('body', '<p>x</p>')->call('save')->assertNotFound();

    expect(Material::count())->toBe(0);
});

it('cannot edit or view a material through another course URL', function () {
    $material = makeMaterial($this->section);
    $otherTeacher = makeUser(Role::Teacher);
    $otherCourse = makeCourse($otherTeacher);

    $this->actingAs($otherTeacher)->get("/kelas/{$otherCourse->id}/materi/{$material->id}")->assertNotFound();
    $this->actingAs($otherTeacher)->get("/kelas/{$otherCourse->id}/materi/{$material->id}/ubah")->assertNotFound();
});

it('forbids students from creating or editing materials', function () {
    $student = makeUser(Role::Student);
    enroll($this->course, $student);
    $material = makeMaterial($this->section);

    $this->actingAs($student)->get("/kelas/{$this->course->id}/materi/buat")->assertForbidden();
    $this->actingAs($student)->get("/kelas/{$this->course->id}/materi/{$material->id}/ubah")->assertForbidden();
    $this->actingAs($student)->get("/kelas/{$this->course->id}/materi/{$material->id}")->assertOk();
});

it('forbids non-members from viewing a material', function () {
    $material = makeMaterial($this->section);

    $this->actingAs(makeUser(Role::Student))->get("/kelas/{$this->course->id}/materi/{$material->id}")->assertForbidden();
});

it('renders each material type', function () {
    $text = makeMaterial($this->section, ['body' => '<p>Isi <strong>tebal</strong></p>']);
    $link = makeMaterial($this->section, ['type' => 'link', 'url' => 'https://contoh.id/x']);
    $video = makeMaterial($this->section, ['type' => 'video', 'url' => 'https://youtu.be/aqz-KE-bpKQ']);
    $external = makeMaterial($this->section, ['type' => 'video', 'url' => 'https://contoh.id/v.mp4']);
    $pdf = makeMaterial($this->section, ['type' => 'file', 'file_path' => 'p.pdf', 'file_name' => 'p.pdf', 'file_size' => 2048, 'mime_type' => 'application/pdf']);
    $image = makeMaterial($this->section, ['type' => 'file', 'file_path' => 'i.png', 'file_name' => 'i.png', 'mime_type' => 'image/png']);
    $zip = makeMaterial($this->section, ['type' => 'file', 'file_path' => 'z.zip', 'file_name' => 'z.zip', 'mime_type' => 'application/zip']);

    $view = fn (Material $m) => Livewire::actingAs($this->teacher)->test(MaterialShow::class, ['course' => $this->course, 'material' => $m]);

    $view($text)->assertSee('<strong>tebal</strong>', false)->assertSee('mathContent', false);
    $view($link)->assertSee('https://contoh.id/x')->assertSee('rel="noopener noreferrer"', false);
    $view($video)->assertSee('youtube-nocookie.com/embed/aqz-KE-bpKQ', false)->assertSee('Putar video');
    $view($external)->assertDontSee('<iframe', false)->assertSee('Buka tautan');
    $view($pdf)->assertSee('pdfViewer', false)->assertSee('2 KB');
    $view($image)->assertSee('<img', false);
    $view($zip)->assertSee('Pratinjau tidak tersedia');
});

it('embeds only YouTube and Vimeo', function (string $url, ?string $expected) {
    expect(VideoEmbed::embedUrl($url))->toBe($expected);
})->with([
    ['https://www.youtube.com/watch?v=aqz-KE-bpKQ', 'https://www.youtube-nocookie.com/embed/aqz-KE-bpKQ'],
    ['https://youtu.be/aqz-KE-bpKQ', 'https://www.youtube-nocookie.com/embed/aqz-KE-bpKQ'],
    ['https://m.youtube.com/shorts/aqz-KE-bpKQ', 'https://www.youtube-nocookie.com/embed/aqz-KE-bpKQ'],
    ['https://vimeo.com/123456789', 'https://player.vimeo.com/video/123456789'],
    ['https://youtube.com.evil.test/watch?v=aqz-KE-bpKQ', null],
    ['https://www.youtube.com/watch?v="><script>', null],
    ['https://contoh.id/video.mp4', null],
    ['bukan url', null],
]);

it('formats upload limits', function () {
    expect(Upload::humanSize(null))->toBe('')
        ->and(Upload::humanSize(512))->toBe('512 B')
        ->and(Upload::humanSize(2048))->toBe('2 KB')
        ->and(Upload::humanSize(5 * 1048576))->toBe('5,0 MB')
        ->and(Upload::maxKb())->toBeLessThanOrEqual(config('courses.max_upload_kb'))
        ->and(Upload::filepondSize())->toMatch('/^\d+(MB|KB)$/');
});
