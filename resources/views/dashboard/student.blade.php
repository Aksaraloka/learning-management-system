@php
    $fmt = fn ($n) => $n === null ? '–' : rtrim(rtrim(number_format((float) $n, 1, ',', '.'), '0'), ',');
    $tz = config('app.timezone');
@endphp

<div class="mt-6 grid gap-4 lg:grid-cols-2" x-reveal>
    <x-card :title="__('dashboard.deadlines')" :padding="false">
        @if ($data['deadlines']->isEmpty())
            <x-empty-state icon="check-circle" :title="__('dashboard.no_deadlines')" />
        @else
            <ul class="divide-y divide-border">
                @foreach ($data['deadlines'] as $d)
                    <li>
                        <a href="{{ $d['url'] }}" wire:navigate class="flex min-h-14 items-center gap-3 px-4 py-2 hover:bg-surface-muted">
                            <x-icon :name="$d['type'] === 'quiz' ? 'academic-cap' : 'clipboard-document-list'" class="text-primary" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium">{{ $d['title'] }}</span>
                                <span class="block truncate text-xs text-text-muted">{{ $d['course'] }}</span>
                            </span>
                            <span class="shrink-0 text-right text-xs {{ $d['at']->lessThan(now()->addDay()) ? 'font-semibold text-warning' : 'text-text-muted' }}">
                                {{ $d['at']->timezone($tz)->translatedFormat('j M, H:i') }}
                                <span class="block">{{ $d['at']->diffForHumans() }}</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>

    <x-card :title="__('dashboard.recent_grades')" :padding="false">
        @if ($data['grades']->isEmpty())
            <x-empty-state icon="trophy" :title="__('dashboard.no_grades')" />
        @else
            <ul class="divide-y divide-border">
                @foreach ($data['grades'] as $g)
                    <li>
                        <a href="{{ $g['url'] }}" wire:navigate class="flex min-h-14 items-center gap-3 px-4 py-2 hover:bg-surface-muted">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium">{{ $g['title'] }}</span>
                                <span class="block truncate text-xs text-text-muted">{{ $g['course'] }}</span>
                            </span>
                            <span class="shrink-0 font-semibold">{{ $fmt($g['score']) }} <span class="text-xs font-normal text-text-muted">/ {{ $fmt($g['max']) }}</span></span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
</div>

<section class="mt-6 space-y-3" aria-labelledby="my-courses">
    <div class="flex items-center justify-between">
        <h2 id="my-courses" class="text-lg font-semibold">{{ __('courses.dashboard.my_courses') }}</h2>
        <a href="{{ route('courses.index') }}" class="text-sm text-primary underline">{{ __('courses.dashboard.all') }}</a>
    </div>

    @if ($data['rows']->isEmpty())
        <x-card><x-empty-state icon="book-open" :title="__('courses.index.empty_title')" :description="__('courses.index.empty_student')" /></x-card>
    @else
        <div class="grid gap-4 lg:grid-cols-2">
            <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1" x-reveal>
                @foreach ($data['rows'] as $row)
                    <li>
                        <a href="{{ route('courses.show', $row['course']) }}" wire:navigate class="flex h-full flex-col gap-2 rounded-card border border-border bg-surface p-4 hover:border-primary">
                            <span class="font-semibold leading-snug">{{ $row['course']->name }}</span>
                            <span class="text-xs text-text-muted">{{ $row['course']->term->name }}</span>
                            <span class="mt-auto flex flex-wrap gap-1.5 pt-1">
                                <x-badge variant="primary">{{ __('dashboard.final') }}: {{ $fmt($row['final']) }}</x-badge>
                                <x-badge>{{ __('gradebook.attendance') }}: {{ $row['attendance'] === null ? '–' : $fmt($row['attendance']).'%' }}</x-badge>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>

            @if ($data['chart']['values'])
                <x-card :title="__('dashboard.grade_per_course')">
                    <x-chart type="bar" :label="__('dashboard.grade_per_course')"
                             :data="['labels' => $data['chart']['labels'], 'datasets' => [['label' => __('dashboard.final'), 'data' => $data['chart']['values'], 'mono' => true]]]"
                             :options="['indexAxis' => 'y', 'scales' => ['x' => ['min' => 0, 'max' => 100]], 'plugins' => ['legend' => ['display' => false]]]" />
                </x-card>
            @endif
        </div>
    @endif
</section>
