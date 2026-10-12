<?php

namespace App\Livewire\Assignments;

use App\Actions\Assignments\ManageAttachments;
use App\Actions\Assignments\SaveAssignment;
use App\Livewire\Concerns\ValidatesUploads;
use App\Models\Assignment;
use App\Models\Course;
use App\Support\Upload;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Form extends Component
{
    use ValidatesUploads, WithFileUploads;

    #[Locked]
    public Course $course;

    #[Locked]
    public ?Assignment $assignment = null;

    public string $section_id = '';

    public string $grade_category_id = '';

    public string $title = '';

    public string $instructions = '';

    public string $due_at = '';

    public string $max_score = '100';

    public bool $allow_late = false;

    public ?TemporaryUploadedFile $upload = null;

    public function mount(Course $course, ?Assignment $assignment = null): void
    {
        $this->course = $course;

        if ($assignment?->exists) {
            abort_unless($assignment->course_id === $course->id, 404);
            Gate::authorize('update', $assignment);

            $this->assignment = $assignment;
            $this->section_id = (string) $assignment->section_id;
            $this->grade_category_id = (string) $assignment->grade_category_id;
            $this->title = $assignment->title;
            $this->instructions = (string) $assignment->instructions;
            $this->due_at = $assignment->due_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '';
            $this->max_score = (string) $assignment->max_score;
            $this->allow_late = $assignment->allow_late;

            return;
        }

        Gate::authorize('create', [Assignment::class, $course]);

        $this->section_id = (string) request()->query('topik', '');
        $this->grade_category_id = (string) ($course->gradeCategories()->where('name', 'Tugas')->value('id') ?? '');
    }

    public function save(SaveAssignment $saveAssignment): void
    {
        Gate::authorize($this->assignment ? 'update' : 'create', $this->assignment ?? [Assignment::class, $this->course]);

        $this->validate([
            'section_id' => ['required', Rule::in($this->course->sections()->pluck('id')->map(fn ($id) => (string) $id)->all())],
            'grade_category_id' => ['nullable', Rule::in($this->course->gradeCategories()->pluck('id')->map(fn ($id) => (string) $id)->all())],
            'title' => ['required', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:200000'],
            'due_at' => ['nullable', 'date_format:Y-m-d H:i'],
            'max_score' => ['required', 'integer', 'between:1,1000'],
            'allow_late' => ['boolean'],
        ], [], [
            'section_id' => term('section'),
            'grade_category_id' => __('assignments.form.category'),
            'title' => __('assignments.form.title'),
            'due_at' => __('assignments.form.due_at'),
            'max_score' => __('assignments.form.max_score'),
        ]);

        $saved = $saveAssignment->handle($this->course, [
            'section_id' => (int) $this->section_id,
            'grade_category_id' => $this->grade_category_id !== '' ? (int) $this->grade_category_id : null,
            'title' => $this->title,
            'instructions' => $this->instructions,
            'due_at' => $this->due_at !== '' ? Carbon::createFromFormat('Y-m-d H:i', $this->due_at, config('app.timezone')) : null,
            'max_score' => (int) $this->max_score,
            'allow_late' => $this->allow_late,
        ], $this->assignment);

        // Tugas baru: lanjut ke halaman ubah agar lampiran bisa ditambahkan.
        $this->redirectRoute($this->assignment ? 'assignments.show' : 'assignments.edit', ['course' => $this->course, 'assignment' => $saved]);
    }

    /**
     * Dipanggil Livewire setelah FilePond selesai mengunggah berkas sementara: langsung jadi lampiran.
     */
    public function updatedUpload(ManageAttachments $attachments): void
    {
        abort_if($this->assignment === null, 404);
        Gate::authorize('update', $this->assignment);

        try {
            $this->validate(['upload' => $this->uploadRules(required: true)], [], ['upload' => __('assignments.form.attachments')]);

            if ($this->assignment->attachments()->count() >= ManageAttachments::MAX_PER_ASSIGNMENT) {
                throw ValidationException::withMessages(['upload' => __('assignments.form.too_many', ['max' => ManageAttachments::MAX_PER_ASSIGNMENT])]);
            }

            $attachments->add($this->assignment, $this->upload);
        } finally {
            $this->reset('upload');
            $this->dispatch('filepond-clear');
        }
    }

    public function removeAttachment(int $id, ManageAttachments $attachments): void
    {
        abort_if($this->assignment === null, 404);
        Gate::authorize('update', $this->assignment);

        $attachments->remove($this->assignment->attachments()->findOrFail($id));
    }

    public function render(): View
    {
        return view('livewire.assignments.form', [
            'sections' => $this->course->sections()->pluck('title', 'id')->all(),
            'categories' => $this->course->gradeCategories()->pluck('name', 'id')->all(),
            'files' => $this->assignment?->attachments()->get() ?? collect(),
            'maxLabel' => Upload::label(),
            'maxSize' => Upload::filepondSize(),
            'mimes' => config('courses.allowed_mimes'),
        ])->title($this->assignment ? __('assignments.form.edit') : __('assignments.form.create'));
    }
}
