<?php

namespace App\Livewire\Admin\Terms;

use App\Models\AcademicTerm;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Index extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public string $starts_at = '';

    public string $ends_at = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', AcademicTerm::class);
    }

    public function edit(int $id): void
    {
        $term = AcademicTerm::findOrFail($id);

        Gate::authorize('update', $term);

        $this->editingId = $term->id;
        $this->name = $term->name;
        $this->starts_at = $term->starts_at->toDateString();
        $this->ends_at = $term->ends_at->toDateString();
    }

    public function cancel(): void
    {
        $this->reset('editingId', 'name', 'starts_at', 'ends_at');
        $this->resetValidation();
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ], [], ['name' => __('ui.terms.name'), 'starts_at' => __('ui.terms.starts_at'), 'ends_at' => __('ui.terms.ends_at')]);

        if ($this->editingId) {
            $term = AcademicTerm::findOrFail($this->editingId);
            Gate::authorize('update', $term);
            $term->update($data);
        } else {
            Gate::authorize('create', AcademicTerm::class);
            AcademicTerm::create($data);
        }

        $this->cancel();
        $this->dispatch('toast', message: __('ui.profile.saved'), type: 'success');
    }

    public function toggleLock(int $id): void
    {
        $term = AcademicTerm::findOrFail($id);

        Gate::authorize('lock', $term);

        $term->update(['is_locked' => ! $term->is_locked]);
    }

    public function delete(int $id): void
    {
        $term = AcademicTerm::findOrFail($id);

        Gate::authorize('delete', $term);

        $term->delete();
    }

    public function render(): View
    {
        return view('livewire.admin.terms.index', [
            'terms' => AcademicTerm::query()->orderByDesc('starts_at')->get(),
        ])->title(__('ui.nav.terms'));
    }
}
