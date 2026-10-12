<?php

namespace App\Livewire\Platform\Tenants;

use App\Actions\Tenants\CreateTenant;
use App\Models\Tenant;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Index extends Component
{
    public string $name = '';

    public string $slug = '';

    public string $level = 'school';

    public string $plan = 'free';

    public string $admin_name = '';

    public string $admin_email = '';

    /** @var array{tenant: string, url: string, email: string, password: string}|null */
    public ?array $created = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', Tenant::class);
    }

    public function create(CreateTenant $createTenant): void
    {
        Gate::authorize('create', Tenant::class);

        $this->slug = strtolower(trim($this->slug));

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'regex:/^[a-z0-9](?:[a-z0-9-]{1,30}[a-z0-9])$/',
                Rule::notIn(config('tenancy.reserved_slugs')),
                Rule::unique('tenants', 'slug'),
            ],
            'level' => ['required', 'in:school,campus'],
            'plan' => ['required', 'string', 'max:30'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
        ], [], [
            'name' => __('ui.tenants.name'), 'slug' => 'slug',
            'admin_name' => __('ui.tenants.admin_name'), 'admin_email' => __('ui.tenants.admin_email'),
        ]);

        $result = $createTenant->handle(
            ['name' => $this->name, 'slug' => $this->slug, 'level' => $this->level, 'plan' => $this->plan],
            ['name' => $this->admin_name, 'email' => $this->admin_email],
        );

        $this->created = [
            'tenant' => $result['tenant']->name,
            'url' => $result['tenant']->url(),
            'email' => $result['admin']->email,
            'password' => $result['password'],
        ];

        $this->reset('name', 'slug', 'level', 'plan', 'admin_name', 'admin_email');
    }

    public function toggleStatus(int $id): void
    {
        $tenant = Tenant::findOrFail($id);

        Gate::authorize('update', $tenant);

        $tenant->update(['status' => $tenant->isActive() ? Tenant::STATUS_SUSPENDED : Tenant::STATUS_ACTIVE]);
    }

    public function render(): View
    {
        return view('livewire.platform.tenants.index', [
            'tenants' => Tenant::query()->withCount('users')->orderBy('name')->get(),
        ])->title(__('ui.nav.tenants'));
    }
}
