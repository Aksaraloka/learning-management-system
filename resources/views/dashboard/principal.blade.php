@php
    $fmt = fn ($n) => $n === null ? '–' : rtrim(rtrim(number_format((float) $n, 1, ',', '.'), '0'), ',');
@endphp

<div class="mt-6 grid gap-4 lg:grid-cols-2" x-reveal>
    <x-card :title="__('dashboard.avg_per_course')">
        @if ($data['chart']['values'])
            <x-chart type="bar" :label="__('dashboard.avg_per_course')"
                     :data="['labels' => array_map(fn ($l) => str($l)->limit(20)->toString(), $data['chart']['labels']), 'datasets' => [['label' => __('dashboard.avg'), 'data' => $data['chart']['values'], 'mono' => true]]]"
                     :options="['indexAxis' => 'y', 'scales' => ['x' => ['min' => 0, 'max' => 100]], 'plugins' => ['legend' => ['display' => false]]]" />
        @else
            <x-empty-state icon="chart-bar" :title="__('dashboard.no_data')" />
        @endif
    </x-card>

    <x-card :title="config('academic.enabled') ? __('dashboard.distribution_estimate') : __('dashboard.distribution')">
        @if (array_sum($data['buckets']) > 0)
            <x-chart type="doughnut" :label="__('dashboard.distribution')"
                     :data="['labels' => array_keys($data['buckets']), 'datasets' => [['label' => __('dashboard.students'), 'data' => array_values($data['buckets'])]]]" />
            <p class="mt-2 text-xs text-text-muted">{{ __('dashboard.letters_hint') }}@if (config('academic.enabled')) {{ __('dashboard.estimate_note') }}@endif</p>
        @else
            <x-empty-state icon="chart-pie" :title="__('dashboard.no_data')" />
        @endif
    </x-card>
</div>

<section class="mt-6 space-y-3" aria-labelledby="all-courses">
    <h2 id="all-courses" class="text-lg font-semibold">{{ __('dashboard.all_courses') }}</h2>
    <x-table>
        <thead>
            <tr>
                <th>{{ term('course') }}</th>
                <th>{{ __('dashboard.lecturer') }}</th>
                <th>{{ term('students') }}</th>
                <th>{{ __('dashboard.avg') }}</th>
                <th>{{ __('gradebook.attendance') }}</th>
                <th>{{ __('dashboard.submission_rate') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data['courses'] as $course)
                @php
                    $s = $data['stats'][$course->id];
                @endphp
                <tr wire:key="c-{{ $course->id }}">
                    <td data-label="{{ term('course') }}"><a href="{{ route('gradebook.index', $course) }}" wire:navigate class="font-medium text-primary hover:underline">{{ $course->name }}</a><span class="block text-xs text-text-muted">{{ $course->term->name }}</span></td>
                    <td data-label="{{ __('dashboard.lecturer') }}">{{ $course->teachers->pluck('name')->join(', ') ?: '–' }}</td>
                    <td data-label="{{ term('students') }}">{{ $s['students'] }}</td>
                    <td data-label="{{ __('dashboard.avg') }}" class="font-semibold">{{ $fmt($s['avg']) }}</td>
                    <td data-label="{{ __('gradebook.attendance') }}">{{ $s['attendance'] === null ? '–' : $fmt($s['attendance']).'%' }}</td>
                    <td data-label="{{ __('dashboard.submission_rate') }}">{{ $s['submission_rate'] === null ? '–' : $fmt($s['submission_rate']).'%' }}</td>
                </tr>
            @endforeach
        </tbody>
    </x-table>
    <p><a href="{{ route('admin.audit.index') }}" wire:navigate class="text-sm text-primary underline">{{ __('audit.title') }}</a></p>
</section>
