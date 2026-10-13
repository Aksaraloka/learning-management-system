<?php

namespace App\Livewire\Admin\Audit;

use App\Models\AuditLog;
use App\Models\Course;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url(as: 'aksi')]
    public string $action = '';

    #[Url(as: 'cari')]
    public string $search = '';

    #[Url(as: 'kelas')]
    public string $course = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', AuditLog::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['action', 'search', 'course'], true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        Gate::authorize('viewAny', AuditLog::class);

        $logs = AuditLog::query()->with('user')
            ->when($this->action !== '', fn ($q) => $q->where('action', $this->action))
            ->when($this->course !== '', fn ($q) => $q->where('course_id', (int) $this->course))
            ->when($this->search !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u->where('name', 'like', '%'.addcslashes($this->search, '%_\\').'%')))
            ->latest('id')
            ->simplePaginate(25);

        return view('livewire.admin.audit.index', [
            'logs' => $logs,
            'actions' => AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action')->all(),
            'courses' => Course::query()->orderBy('name')->pluck('name', 'id')->all(),
            'courseNames' => Course::withTrashed()->whereIn('id', $logs->pluck('course_id')->filter())->pluck('name', 'id')->all(),
        ])->title(__('audit.title'));
    }
}
