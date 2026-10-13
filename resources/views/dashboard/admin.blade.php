@php
    $tz = config('app.timezone');
@endphp

<div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4" x-reveal>
    <x-card :title="__('ui.nav.users')"><p class="text-3xl font-bold" x-countup>{{ $data['counts']['users'] }}</p></x-card>
    <x-card :title="__('ui.nav.classes')"><p class="text-3xl font-bold" x-countup>{{ $data['counts']['courses'] }}</p></x-card>
    <x-card :title="__('ui.nav.terms')"><p class="text-3xl font-bold" x-countup>{{ $data['counts']['terms'] }}</p></x-card>
    <x-card :title="__('dashboard.locked_terms')"><p class="text-3xl font-bold" x-countup>{{ $data['counts']['locked'] }}</p></x-card>
</div>

<div class="mt-6 grid gap-4 lg:grid-cols-2" x-reveal>
    <x-card :title="__('dashboard.users_by_role')">
        @if ($data['roles']['values'])
            <x-chart type="doughnut" :label="__('dashboard.users_by_role')"
                     :data="['labels' => $data['roles']['labels'], 'datasets' => [['label' => __('ui.nav.users'), 'data' => $data['roles']['values']]]]" />
        @else
            <x-empty-state icon="users" :title="__('dashboard.no_data')" />
        @endif
    </x-card>

    <x-card :title="__('dashboard.courses_per_term')">
        @if ($data['terms']['values'])
            <x-chart type="bar" :label="__('dashboard.courses_per_term')"
                     :data="['labels' => $data['terms']['labels'], 'datasets' => [['label' => __('ui.nav.classes'), 'data' => $data['terms']['values'], 'mono' => true]]]"
                     :options="['scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]], 'plugins' => ['legend' => ['display' => false]]]" />
        @else
            <x-empty-state icon="calendar" :title="__('dashboard.no_data')" />
        @endif
    </x-card>
</div>

<section class="mt-6 space-y-3" aria-labelledby="recent-audit">
    <div class="flex items-center justify-between">
        <h2 id="recent-audit" class="text-lg font-semibold">{{ __('dashboard.recent_audit') }}</h2>
        <a href="{{ route('admin.audit.index') }}" wire:navigate class="text-sm text-primary underline">{{ __('dashboard.all') }}</a>
    </div>
    <x-card :padding="false">
        @if ($data['audit']->isEmpty())
            <x-empty-state icon="shield-check" :title="__('audit.empty')" />
        @else
            <ul class="divide-y divide-border">
                @foreach ($data['audit'] as $log)
                    <li class="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-2.5 text-sm">
                        <x-badge>{{ $log->label() }}</x-badge>
                        <span class="min-w-0 flex-1 truncate">{{ $log->user?->name ?? __('audit.system') }}</span>
                        <span class="text-xs text-text-muted">{{ $log->created_at->timezone($tz)->diffForHumans() }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
</section>
