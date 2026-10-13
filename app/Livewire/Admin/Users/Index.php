<?php

namespace App\Livewire\Admin\Users;

use App\Actions\Users\ResetUserPassword;
use App\Enums\Role;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $role = '';

    /** @var array{name: string, password: string}|null */
    public ?array $revealed = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', User::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRole(): void
    {
        $this->resetPage();
    }

    public function resetPassword(int $id, ResetUserPassword $reset): void
    {
        $target = User::findOrFail($id);

        Gate::authorize('resetPassword', $target);

        $this->revealed = ['name' => $target->name, 'password' => $reset->handle($target)];

        Audit::record('user.password_reset', $target, ['name' => $target->name]);
    }

    public function dismiss(): void
    {
        $this->revealed = null;
    }

    public function render(): View
    {
        $users = User::query()
            ->with('roles')
            ->when($this->search !== '', function ($q) {
                $term = '%'.addcslashes($this->search, '%_\\').'%';

                $q->where(fn ($w) => $w->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('identity_number', 'like', $term));
            })
            ->when($this->role !== '' && Role::tryFrom($this->role), fn ($q) => $q->role($this->role))
            ->orderBy('name')
            ->simplePaginate(20);

        return view('livewire.admin.users.index', [
            'users' => $users,
            'roleOptions' => collect(Role::importable())->prepend(Role::Admin)->mapWithKeys(fn (Role $r) => [$r->value => $r->label()])->all(),
        ])->title(__('ui.nav.users'));
    }
}
