<?php

namespace App\Livewire\Announcements;

use App\Actions\Announcements\SaveAnnouncement;
use App\Models\Announcement;
use App\Models\Course;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    /** '' = semua, 'umum' = pengumuman universitas, selain itu id kelas. */
    #[Url(as: 'kelas')]
    public string $scope = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $course_id = '';

    public string $title = '';

    public string $body = '';

    public bool $is_pinned = false;

    public function updatedScope(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
        $this->course_id = $this->scope !== '' && $this->scope !== 'umum' && isset($this->postableCourses()[(int) $this->scope]) ? $this->scope : (string) (array_key_first($this->postableCourses()) ?? '');
    }

    public function edit(int $id): void
    {
        $announcement = Announcement::query()->visibleTo(Auth::user())->findOrFail($id);

        Gate::authorize('update', $announcement);

        $this->editingId = $announcement->id;
        $this->course_id = (string) ($announcement->course_id ?? 'umum');
        $this->title = $announcement->title;
        $this->body = $announcement->body;
        $this->is_pinned = $announcement->is_pinned;
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(SaveAnnouncement $save): void
    {
        $existing = $this->editingId ? Announcement::query()->visibleTo(Auth::user())->findOrFail($this->editingId) : null;
        $courses = $this->postableCourses();

        $this->validate([
            'course_id' => $existing ? ['nullable'] : ['required', 'in:'.implode(',', array_merge(array_map('strval', array_keys($courses)), Auth::user()->isAdmin() ? ['umum'] : []))],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:100000', fn ($a, $v, $fail) => trim(strip_tags((string) $v)) === '' ? $fail(__('validation.required', ['attribute' => __('announcements.body')])) : null],
        ], [], ['title' => __('announcements.title'), 'body' => __('announcements.body'), 'course_id' => __('announcements.audience')]);

        $courseId = $this->course_id === 'umum' ? null : (int) $this->course_id;

        if ($existing) {
            Gate::authorize('update', $existing);
        } else {
            Gate::authorize('create', [Announcement::class, $courseId ? Course::query()->findOrFail($courseId) : null]);
        }

        $save->handle(Auth::user(), [
            'course_id' => $courseId,
            'title' => $this->title,
            'body' => $this->body,
            'is_pinned' => $this->is_pinned,
        ], $existing);

        $this->resetForm();
        $this->dispatch('toast', message: __('announcements.saved'), type: 'success');
    }

    public function togglePin(int $id): void
    {
        $announcement = Announcement::query()->visibleTo(Auth::user())->findOrFail($id);

        Gate::authorize('update', $announcement);

        $announcement->update(['is_pinned' => ! $announcement->is_pinned]);
    }

    public function delete(int $id): void
    {
        $announcement = Announcement::query()->visibleTo(Auth::user())->findOrFail($id);

        Gate::authorize('delete', $announcement);

        $announcement->delete();
    }

    private function resetForm(): void
    {
        $this->reset(['showForm', 'editingId', 'course_id', 'title', 'body', 'is_pinned']);
        $this->resetErrorBag();
    }

    /**
     * Kelas tempat pengguna boleh membuat pengumuman (admin: semua).
     *
     * @return array<int, string>
     */
    private function postableCourses(): array
    {
        $user = Auth::user();

        if ($user->isAdmin()) {
            return Course::query()->orderBy('name')->pluck('name', 'id')->all();
        }

        return Course::query()->whereHas('enrollments', fn ($q) => $q->where('user_id', $user->id)->where('role', 'teacher'))
            ->orderBy('name')->pluck('name', 'id')->all();
    }

    public function render(): View
    {
        $user = Auth::user();
        $postable = $this->postableCourses();

        $items = Announcement::query()->visibleTo($user)->with(['author', 'course'])
            ->when($this->scope === 'umum', fn ($q) => $q->whereNull('course_id'))
            ->when($this->scope !== '' && $this->scope !== 'umum', fn ($q) => $q->where('course_id', (int) $this->scope))
            ->orderByDesc('is_pinned')->latest()->latest('id')
            ->simplePaginate(10);

        return view('livewire.announcements.index', [
            'items' => $items,
            'canPost' => $user->isAdmin() || $postable !== [],
            'postable' => $postable,
            'isAdmin' => $user->isAdmin(),
            'scopes' => Course::query()->visibleTo($user)->orderBy('name')->pluck('name', 'id')->all(),
        ])->title(__('announcements.title_page'));
    }
}
