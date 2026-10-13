@php
    $fmt = fn ($n) => $n === null ? '–' : rtrim(rtrim(number_format((float) $n, 1, ',', '.'), '0'), ',');
    $tz = config('app.timezone');
    $finals = $data['rows']->pluck('final')->filter(fn ($v) => $v !== null);
    $attendances = $data['rows']->pluck('attendance')->filter(fn ($v) => $v !== null);
    $avg = $finals->isNotEmpty() ? $finals->avg() : null;
    $att = $attendances->isNotEmpty() ? $attendances->avg() : null;
    $urgent = $data['deadlines']->filter(fn ($d) => $d['at']->lessThan(now()->addDay()))->count();
@endphp

{{-- Ringkasan angka: dua kolom di HP agar tidak sempit, tiga kolom mulai layar sedang. --}}
<section aria-label="{{ __('dashboard.summary') }}" class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3" x-reveal>
    <div class="rounded-card border border-border bg-surface p-4">
        <p class="text-xs font-medium text-text-muted">{{ __('dashboard.avg_final') }}</p>
        <p class="mt-1 text-2xl font-bold leading-none sm:text-3xl">{{ $fmt($avg) }}</p>
    </div>
    <div class="rounded-card border border-border bg-surface p-4">
        <p class="text-xs font-medium text-text-muted">{{ __('gradebook.attendance') }}</p>
        <p class="mt-1 text-2xl font-bold leading-none sm:text-3xl">{{ $att === null ? '–' : $fmt($att).'%' }}</p>
    </div>
    <div class="col-span-2 rounded-card border border-border bg-surface p-4 sm:col-span-1">
        <p class="text-xs font-medium text-text-muted">{{ __('dashboard.deadlines') }}</p>
        <p class="mt-1 text-2xl font-bold leading-none sm:text-3xl">{{ $data['deadlines']->count() }}</p>
        @if ($urgent) <p class="mt-1 text-xs font-semibold text-warning">{{ __('dashboard.urgent', ['n' => $urgent]) }}</p> @endif
    </div>
</section>

<div class="mt-4 grid items-start gap-4 lg:grid-cols-2" x-reveal>
    <x-card :title="__('dashboard.deadlines')" :padding="false" class="min-w-0">
        @if ($data['deadlines']->isEmpty())
            <x-empty-state icon="check-circle" :title="__('dashboard.no_deadlines')" />
        @else
            <ul class="divide-y divide-border">
                @foreach ($data['deadlines'] as $d)
                    @php
                        $soon = $d['at']->lessThan(now()->addDay());
                    @endphp
                    <li>
                        <a href="{{ $d['url'] }}" wire:navigate class="flex min-h-14 items-center gap-3 px-4 py-2.5 hover:bg-surface-muted">
                            <x-icon :name="$d['type'] === 'quiz' ? 'academic-cap' : 'clipboard-document-list'" class="shrink-0 text-primary" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium">{{ $d['title'] }}</span>
                                <span class="block truncate text-xs text-text-muted">{{ $d['course'] }}</span>
                            </span>
                            <span class="shrink-0 text-right text-xs leading-snug {{ $soon ? 'font-semibold text-warning' : 'text-text-muted' }}">
                                {{ $d['at']->timezone($tz)->translatedFormat('j M, H:i') }}
                                <span class="block font-normal">{{ $d['at']->diffForHumans() }}</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>

    <x-card :title="__('dashboard.recent_grades')" :padding="false" class="min-w-0">
        @if ($data['grades']->isEmpty())
            <x-empty-state icon="trophy" :title="__('dashboard.no_grades')" />
        @else
            <ul class="divide-y divide-border">
                @foreach ($data['grades'] as $g)
                    <li>
                        <a href="{{ $g['url'] }}" wire:navigate class="flex min-h-14 items-center gap-3 px-4 py-2.5 hover:bg-surface-muted">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium">{{ $g['title'] }}</span>
                                <span class="block truncate text-xs text-text-muted">{{ $g['course'] }}</span>
                            </span>
                            <span class="shrink-0 font-semibold">{{ $fmt($g['score']) }}<span class="text-xs font-normal text-text-muted"> / {{ $fmt($g['max']) }}</span></span>
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

    @if ($data['rows']->isEmpty())
        <x-card><x-empty-state icon="book-open" :title="__('courses.index.empty_title')" :description="__('courses.index.empty_student')" /></x-card>
    @else
        <ul class="grid gap-4 grid-cols-[repeat(auto-fill,minmax(14rem,1fr))]" x-reveal>
            @foreach ($data['rows'] as $row)
                <li class="min-w-0">
                    <a href="{{ route('courses.show', $row['course']) }}" wire:navigate class="flex h-full flex-col overflow-hidden rounded-card border border-border bg-surface transition hover:border-primary">
                        <img src="{{ $row['course']->bannerUrl() }}" alt="" loading="lazy" referrerpolicy="no-referrer" class="h-24 w-full object-cover">
                        <div class="flex flex-1 flex-col gap-3 p-4">
                            <div class="min-w-0">
                                <span class="block truncate font-semibold leading-snug">{{ $row['course']->name }}</span>
                                <span class="block truncate text-xs text-text-muted">{{ $row['course']->term->name }}</span>
                            </div>
                            <x-progress :done="$row['progress']['done']" :total="$row['progress']['total']" :percent="$row['progress']['percent']" />
                            <dl class="mt-auto grid grid-cols-2 gap-3 text-xs">
                                <div>
                                    <dt class="text-text-muted">{{ __('dashboard.final') }}</dt>
                                    <dd class="mt-0.5 text-base font-bold">{{ $fmt($row['final']) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-text-muted">{{ __('gradebook.attendance') }}</dt>
                                    <dd class="mt-0.5 text-base font-bold">{{ $row['attendance'] === null ? '–' : $fmt($row['attendance']).'%' }}</dd>
                                </div>
                            </dl>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>

        @if ($data['chart']['values'])
            <x-card :title="__('dashboard.grade_per_course')" class="min-w-0" x-reveal>
                <x-chart type="bar" :label="__('dashboard.grade_per_course')"
                         :data="['labels' => array_map(fn ($l) => str($l)->limit(20)->toString(), $data['chart']['labels']), 'datasets' => [['label' => __('dashboard.final'), 'data' => $data['chart']['values'], 'mono' => true]]]"
                         :options="['indexAxis' => 'y', 'scales' => ['x' => ['min' => 0, 'max' => 100]], 'plugins' => ['legend' => ['display' => false]]]"
                         height="h-56" />
            </x-card>
        @endif
    @endif
</section>
