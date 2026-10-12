<?php

namespace App\Livewire\Materials;

use App\Actions\Materials\DeleteMaterial;
use App\Models\Course;
use App\Models\Material;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Show extends Component
{
    #[Locked]
    public Course $course;

    #[Locked]
    public Material $material;

    public function mount(Course $course, Material $material): void
    {
        $material->loadMissing('section');

        // Materi harus milik kelas di URL, bila tidak URL bisa dipakai menebak materi kelas lain.
        abort_unless($material->section->course_id === $course->id, 404);

        Gate::authorize('view', $material);

        $this->course = $course;
        $this->material = $material;
    }

    public function delete(DeleteMaterial $deleteMaterial): void
    {
        Gate::authorize('delete', $this->material);

        $deleteMaterial->handle($this->material);

        $this->redirectRoute('courses.show', $this->course);
    }

    public function render(): View
    {
        return view('livewire.materials.show', [
            'canManage' => Gate::allows('update', $this->course),
        ])->title($this->material->title);
    }
}
