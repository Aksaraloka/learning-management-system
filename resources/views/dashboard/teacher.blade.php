@php
    $fmt = fn ($n) => $n === null ? '–' : rtrim(rtrim(number_format((float) $n, 1, ',', '.'), '0'), ',');
    $tz = config('app.timezone');
    $pending = $data['needsGrading']->sum('count');
    $avgs = $data['stats']->pluck('avg')->filter(fn ($v) => $v !== null);
    $atts = $data['stats']->pluck('attendance')->filter(fn ($v) => $v !== null);
@endphp

<section aria-label="{{ __('dashboard.summary') }}" class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3" x-reveal>
    <div class="rounded-card border border-border bg-surface p-4">
        <p class="text-xs font-medium text-text-muted">{{ __('dashboard.needs_grading') }}</p>
        <p class="mt-1 text-2xl font-bold leading-none sm:text-3xl">{{ $pending }}</p>
    </div>
    <div class="rounded-card border border-border bg-surface p-4">
        <p class="text-xs font-medium text-text-muted">{{ __('dashboard.avg') }}</p>
        <p class="mt-1 text-2xl font-bold leading-none sm:text-3xl">{{ $fmt($avgs->isNotEmpty() ? $avgs->avg() : null) }}</p>
    </div>
    <div class="col-span-2 rounded-card border border-border bg-surface p-4 sm:col-span-1">
        <p class="text-xs font-medium text-text-muted">{{ __('gradebook.attendance') }}</p>
        <p class="mt-1 text-2xl font-bold leading-none sm:text-3xl">{{ $atts->isNotEmpty() ? $fmt($atts->avg()).'%' : '–' }}</p>
    </div>
</section>

<div class="mt-4 grid items-start gap-4 lg:grid-cols-2" x-reveal>
    <x-card :title="__('dashboard.needs_grading')" :padding="false" class="min-w-0">
        @if ($data['needsGrading']->isEmpty())
            <x-empty-state icon="check-circle" :title="__('dashboard.all_graded')" />
        @else
            <ul class="divide-y divide-border">
                @foreach ($data['needsGrading'] as $item)
                    <li>
                        <a href="{{ $item['url'] }}" wire:navigate class="flex min-h-14 items-center gap-3 px-4 py-2.5 hover:bg-surface-muted">
                            <x-icon :name="$item['type'] === 'quiz' ? 'academic-cap' : 'clipboard-document-list'" class="shrink-0 text-primary" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium">{{ $item['title'] }}</span>
                                <span class="block truncate text-xs text-text-muted">{{ $item['course'] }}</span>
                            </span>
                            <x-badge variant="info" class="shrink-0">{{ __('dashboard.n_pending', ['n' => $item['count']]) }}</x-badge>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>

    <x-card :title="__('dashboard.due_soon')" :padding="false" class="min-w-0">
        @if ($data['soon']->isEmpty())
            <x-empty-state icon="calendar-days" :title="__('dashboard.no_due_soon')" />
        @else
            <ul class="divide-y divide-border">
                @foreach ($data['soon'] as $a)
                    <li>
                        <a href="{{ route('assignments.show', ['course' => $a->course_id, 'assignment' => $a->id]) }}" wire:navigate class="flex min-h-14 items-center gap-3 px-4 py-2.5 hover:bg-surface-muted">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium">{{ $a->title }}</span>
                                <span class="block truncate text-xs text-text-muted">{{ $a->course->name }} · {{ $a->due_at->timezone($tz)->translatedFormat('j M, H:i') }}</span>
                            </span>
                            <span class="shrink-0 text-right text-xs text-text-muted">{{ __('dashboard.submitted_count', ['n' => $a->submitted_count, 'total' => $data['studentCounts'][$a->course_id] ?? 0]) }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
</div>

<section class="mt-6 space-y-3" aria-labelledby="my-courses">
    <div class="flex items-center justify-between gap-3">
        <h2 id="my-courses" class="text-lg font-semibold">{{ __('courses.dashboard.my_courses') }}</h2>
        <a href="{{ route('courses.index') }}" class="text-sm text-primary underline">{{ __('courses.dashboard.all') }}</a>
    </div>

    @if ($data['courses']->isEmpty())
        <x-card><x-empty-state icon="book-open" :title="__('courses.index.empty_title')" :description="__('courses.index.empty_manager')" /></x-card>
    @else
        <ul class="grid gap-4 grid-cols-[repeat(auto-fill,minmax(14rem,1fr))]" x-reveal>
            @foreach ($data['courses'] as $course)
                @php
                    $s = $data['stats'][$course->id];
                @endphp
                <li class="min-w-0">
                    <a href="{{ route('courses.show', $course) }}" wire:navigate class="flex h-full flex-col overflow-hidden rounded-card border border-border bg-surface transition hover:border-primary">
                        <img src="{{ $course->bannerUrl() }}" alt="" loading="lazy" referrerpolicy="no-referrer" class="h-24 w-full object-cover">
                        <div class="flex flex-1 flex-col gap-3 p-4">
                            <div class="min-w-0">
                                <span class="block truncate font-semibold leading-snug">{{ $course->name }}</span>
                                <span class="block truncate text-xs text-text-muted">{{ $course->term->name }} · {{ __('courses.index.members_count', ['n' => $s['students']]) }}</span>
                            </div>
                            <dl class="mt-auto grid grid-cols-3 gap-2 text-xs">
                                <div><dt class="text-text-muted">{{ __('dashboard.avg') }}</dt><dd class="mt-0.5 text-base font-bold">{{ $fmt($s['avg']) }}</dd></div>
                                <div><dt class="text-text-muted">{{ __('gradebook.attendance') }}</dt><dd class="mt-0.5 text-base font-bold">{{ $s['attendance'] === null ? '–' : $fmt($s['attendance']).'%' }}</dd></div>
                                <div><dt class="text-text-muted">{{ __('dashboard.submission_rate') }}</dt><dd class="mt-0.5 text-base font-bold">{{ $s['submission_rate'] === null ? '–' : $fmt($s['submission_rate']).'%' }}</dd></div>
                            </dl>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>

        @if ($data['chart']['values'])
            <x-card :title="__('dashboard.avg_per_course')" class="min-w-0" x-reveal>
                <x-chart type="bar" :label="__('dashboard.avg_per_course')"
                         :data="['labels' => array_map(fn ($l) => str($l)->limit(20)->toString(), $data['chart']['labels']), 'datasets' => [['label' => __('dashboard.avg'), 'data' => $data['chart']['values'], 'mono' => true]]]"
                         :options="['indexAxis' => 'y', 'scales' => ['x' => ['min' => 0, 'max' => 100]], 'plugins' => ['legend' => ['display' => false]]]"
                         height="h-56" />
            </x-card>
        @endif
    @endif
</section>
