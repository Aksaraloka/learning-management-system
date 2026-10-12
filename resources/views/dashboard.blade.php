<x-layouts.app :title="__('ui.nav.home')">
    <x-card>
        <h2 class="text-lg font-semibold">{{ __('ui.dashboard.welcome', ['name' => $user->name]) }}</h2>
        <div class="mt-2 flex flex-wrap gap-1">
            @foreach ($user->roles as $role)
                <x-badge variant="primary">{{ \App\Enums\Role::tryFrom($role->name)?->label() ?? $role->name }}</x-badge>
            @endforeach
        </div>
    </x-card>

    @if ($stats)
        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            <x-card :title="__('ui.nav.users')"><p class="text-3xl font-bold">{{ $stats['users'] }}</p></x-card>
            <x-card :title="__('ui.nav.terms')"><p class="text-3xl font-bold">{{ $stats['terms'] }}</p></x-card>
        </div>
    @endif
</x-layouts.app>
