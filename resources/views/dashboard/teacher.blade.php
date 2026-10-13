@php
    $fmt = fn ($n) => $n === null ? '–' : rtrim(rtrim(number_format((float) $n, 1, ',', '.'), '0'), ',');
    $tz = config('app.timezone');
@endphp

<div class="mt-6 grid gap-4 lg:grid-cols-2" x-reveal>
    <x-card :title="__('dashboard.needs_grading')" :padding="false">
        @if ($data['needsGrading']->isEmpty())
            <x-empty-state icon="check-circle" :title="__('dashboard.all_graded')" />
        @else
            <ul class="divide-y divide-border">
                @foreach ($data['needsGrading'] as $item)
                    <li>
                        <a href="{{ $item['url'] }}" wire:navigate class="flex min-h-14 items-center gap-3 px-4 py-2 hover:bg-surface-muted">
                            <x-icon :name="$item['type'] === 'quiz' ? 'academic-cap' : 'clipboard-document-list'" class="text-primary" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium">{{ $item['title'] }}</span>
                                <span class="block truncate text-xs text-text-muted">{{ $item['course'] }}</span>
                            </span>
                            <x-badge variant="info">{{ __('dashboard.n_pending', ['n' => $item['count']]) }}</x-badge>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>

    <x-card :title="__('dashboard.due_soon')" :padding="false">
        @if ($data['soon']->isEmpty())
            <x-empty-state icon="calendar-days" :title="__('dashboard.no_due_soon')" />
        @else
            <ul class="divide-y divide-border">
                @foreach ($data['soon'] as $a)
                    <li>
                        <a href="{{ route('assignments.show', ['course' => $a->course_id, 'assignment' => $a->id]) }}" wire:navigate class="flex min-h-14 items-center gap-3 px-4 py-2 hover:bg-surface-muted">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium">{{ $a->title }}</span>
                                <span class="block truncate text-xs text-text-muted">{{ $a->course->name }} · {{ $a->due_at->timezone($tz)->translatedFormat('j M, H:i') }}</span>
                            </span>
                            <span class="shrink-0 text-xs text-text-muted">{{ __('dashboard.submitted_count', ['n' => $a->submitted_count, 'total' => $data['studentCounts'][$a->course_id] ?? 0]) }}</span>
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

    @if ($data['courses']->isEmpty())
        <x-card><x-empty-state icon="book-open" :title="__('courses.index.empty_title')" :description="__('courses.index.empty_manager')" /></x-card>
    @else
        <div class="grid gap-4 lg:grid-cols-2">
            <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1" x-reveal>
                @foreach ($data['courses'] as $course)
                    @php
                        $s = $data['stats'][$course->id];
                    @endphp
                    <li>
                        <a href="{{ route('courses.show', $course) }}" wire:navigate class="flex h-full flex-col gap-2 rounded-card border border-border bg-surface p-4 hover:border-primary">
                            <span class="font-semibold leading-snug">{{ $course->name }}</span>
                            <span class="text-xs text-text-muted">{{ $course->term->name }} · {{ __('courses.index.members_count', ['n' => $s['students']]) }}</span>
                            <span class="mt-auto flex flex-wrap gap-1.5 pt-1">
                                <x-badge variant="primary">{{ __('dashboard.avg') }}: {{ $fmt($s['avg']) }}</x-badge>
                                <x-badge>{{ __('gradebook.attendance') }}: {{ $s['attendance'] === null ? '–' : $fmt($s['attendance']).'%' }}</x-badge>
                                <x-badge>{{ __('dashboard.submission_rate') }}: {{ $s['submission_rate'] === null ? '–' : $fmt($s['submission_rate']).'%' }}</x-badge>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>

            @if ($data['chart']['values'])
                <x-card :title="__('dashboard.avg_per_course')">
                    <x-chart type="bar" :label="__('dashboard.avg_per_course')"
                             :data="['labels' => $data['chart']['labels'], 'datasets' => [['label' => __('dashboard.avg'), 'data' => $data['chart']['values'], 'mono' => true]]]"
                             :options="['indexAxis' => 'y', 'scales' => ['x' => ['min' => 0, 'max' => 100]], 'plugins' => ['legend' => ['display' => false]]]" />
                </x-card>
            @endif
        </div>
    @endif
</section>
