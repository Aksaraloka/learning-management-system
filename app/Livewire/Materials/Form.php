<?php

namespace App\Livewire\Materials;

use App\Actions\Materials\SaveMaterial;
use App\Enums\MaterialType;
use App\Models\Course;
use App\Models\Material;
use App\Support\Upload;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Form extends Component
{
    use WithFileUploads;

    #[Locked]
    public Course $course;

    #[Locked]
    public ?Material $material = null;

    public string $section_id = '';

    public string $type = 'text';

    public string $title = '';

    public string $body = '';

    public string $url = '';

    public ?TemporaryUploadedFile $upload = null;

    public function mount(Course $course, ?Material $material = null): void
    {
        Gate::authorize('update', $course);

        $this->course = $course;
        $this->section_id = (string) request()->query('pertemuan', '');

        if ($material?->exists) {
            $material->loadMissing('section');
            abort_unless($material->section->course_id === $course->id, 404);

            $this->material = $material;
            $this->section_id = (string) $material->section_id;
            $this->type = $material->type->value;
            $this->title = $material->title;
            $this->body = (string) $material->body;
            $this->url = (string) $material->url;
        }
    }

    public function save(SaveMaterial $saveMaterial): void
    {
        $section = $this->course->sections()->findOrFail((int) $this->section_id);

        Gate::authorize($this->material ? 'update' : 'create', $this->material ?? [Material::class, $section]);

        $type = MaterialType::from($this->type);
        $needsFile = $type === MaterialType::File && ! ($this->material?->file_path);

        $this->validate([
            'section_id' => ['required', Rule::in($this->course->sections()->pluck('id')->map(fn ($id) => (string) $id)->all())],
            'type' => ['required', Rule::enum(MaterialType::class)],
            'title' => ['required', 'string', 'max:255'],
            'body' => $type === MaterialType::Text ? ['required', 'string', 'max:200000', fn ($a, $v, $fail) => trim(strip_tags((string) $v)) === '' ? $fail(__('validation.required', ['attribute' => __('courses.materials.body')])) : null] : ['nullable'],
            'url' => in_array($type, [MaterialType::Link, MaterialType::Video], true) ? ['required', 'url:http,https', 'max:2048'] : ['nullable'],
            'upload' => $type === MaterialType::File
                ? [$needsFile ? 'required' : 'nullable', 'file', 'max:'.Upload::maxKb(), 'mimes:'.implode(',', config('courses.allowed_mimes')), 'extensions:'.implode(',', config('courses.allowed_mimes'))]
                : ['nullable'],
        ], [], [
            'section_id' => term('section'),
            'title' => __('courses.materials.title'),
            'body' => __('courses.materials.body'),
            'url' => 'URL',
            'upload' => __('courses.materials.file'),
        ]);

        $saveMaterial->handle(
            $section,
            ['type' => $type, 'title' => $this->title, 'body' => $this->body, 'url' => $this->url],
            $type === MaterialType::File ? $this->upload : null,
            $this->material,
        );

        $this->redirectRoute('courses.show', $this->course);
    }

    public function render(): View
    {
        return view('livewire.materials.form', [
            'sections' => $this->course->sections()->pluck('title', 'id')->all(),
            'maxLabel' => Upload::label(),
            'maxSize' => Upload::filepondSize(),
            'mimes' => config('courses.allowed_mimes'),
        ])->title($this->material ? __('courses.materials.edit') : __('courses.materials.add'));
    }
}
