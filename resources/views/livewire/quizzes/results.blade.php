@php
    $tz = config('app.timezone');
    $fmt = fn ($n) => $n === null ? '–' : rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
@endphp

<div class="space-y-4">
    <a href="{{ route('quizzes.show', ['course' => $course, 'quiz' => $quiz]) }}" wire:navigate class="inline-flex min-h-11 items-center gap-1 text-sm text-primary">
        <x-icon name="arrow-left" class="size-4" /> {{ __('quizzes.results.back') }}: {{ $quiz->title }}
    </a>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-6">
        @foreach ([
            ['stat_took', $stats['took'].' / '.$stats['students']],
            ['stat_running', $stats['running']],
            ['stat_pending', $stats['pending']],
            ['stat_average', $fmt($stats['average'])],
            ['stat_highest', $fmt($stats['highest'])],
            ['stat_lowest', $fmt($stats['lowest'])],
        ] as [$key, $value])
            <div class="rounded-card border border-border bg-surface p-3">
                <p class="text-xs text-text-muted">{{ __('quizzes.results.'.$key) }}</p>
                <p class="mt-1 text-xl font-semibold">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <x-card :padding="false">
        <div class="flex flex-wrap items-end gap-3 border-b border-border p-4">
            <div class="min-w-48 flex-1">
                <x-input name="search" type="search" wire:model.live.debounce.300ms="search" :label="__('quizzes.results.search')" />
            </div>
            <div class="flex flex-wrap gap-1.5 text-xs" role="group">
                @foreach (['all' => 'filter_all', 'needs' => 'filter_needs', 'graded' => 'filter_graded'] as $key => $label)
                    <button type="button" wire:click="$set('filter', '{{ $key }}')" aria-pressed="{{ $filter === $key ? 'true' : 'false' }}"
                            @class(['min-h-9 rounded-full border px-3 font-medium', 'border-primary bg-primary text-primary-fg' => $filter === $key, 'border-border text-text-muted hover:bg-surface-muted' => $filter !== $key])>
                        {{ __('quizzes.results.'.$label) }}
                    </button>
                @endforeach
            </div>
            @if ($canManage)
                <x-button variant="secondary" size="sm" icon="arrow-path" wire:click="recompute" wire:loading.attr="disabled" wire:target="recompute">{{ __('quizzes.questions.recompute') }}</x-button>
            @endif
        </div>

        @if ($attempts->isEmpty())
            <x-empty-state icon="chart-bar" :title="__('quizzes.results.empty')" />
        @else
            <x-table>
                <thead>
                    <tr>
                        <th>{{ __('quizzes.results.col_student') }}</th>
                        <th>{{ __('quizzes.results.col_attempt') }}</th>
                        <th>{{ __('quizzes.results.col_finished') }}</th>
                        <th>{{ __('quizzes.results.col_status') }}</th>
                        <th>{{ __('quizzes.results.col_score') }}</th>
                        <th><span class="sr-only">{{ __('quizzes.results.open') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($attempts as $attempt)
                        <tr wire:key="attempt-{{ $attempt->id }}">
                            <td data-label="{{ __('quizzes.results.col_student') }}">
                                <span class="font-medium">{{ $attempt->user->name }}</span>
                                <span class="block text-xs text-text-muted">{{ $attempt->user->identity_number }}</span>
                            </td>
                            <td data-label="{{ __('quizzes.results.col_attempt') }}">{{ $attempt->attempt_no }}</td>
                            <td data-label="{{ __('quizzes.results.col_finished') }}">{{ $attempt->finished_at?->timezone($tz)->translatedFormat('j M Y H:i') }}</td>
                            <td data-label="{{ __('quizzes.results.col_status') }}">
                                <x-badge :variant="$attempt->status === \App\Enums\AttemptStatus::Graded ? 'success' : 'info'">{{ $attempt->status->label() }}</x-badge>
                            </td>
                            <td data-label="{{ __('quizzes.results.col_score') }}" class="font-semibold">{{ $attempt->status === \App\Enums\AttemptStatus::Graded ? $fmt($attempt->score).' / '.$fmt($attempt->max_score) : '–' }}</td>
                            <td>
                                <x-button variant="secondary" size="sm" :href="route('quizzes.review', ['course' => $course, 'quiz' => $quiz, 'attempt' => $attempt])" wire:navigate>{{ __('quizzes.results.open') }}</x-button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        @endif
    </x-card>
</div>
