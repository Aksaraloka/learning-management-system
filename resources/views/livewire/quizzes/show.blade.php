@php
    $tz = config('app.timezone');
    $opens = $quiz->opens_at?->timezone($tz);
    $closes = $quiz->closes_at?->timezone($tz);
    $showAt = $quiz->show_result_at?->timezone($tz);
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
@endphp

<div class="mx-auto max-w-3xl space-y-4">
    <a href="{{ route('courses.show', $course) }}" wire:navigate class="inline-flex min-h-11 items-center gap-1 text-sm text-primary">
        <x-icon name="arrow-left" class="size-4" /> {{ $course->name }}
    </a>

    <x-card>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-2">
                <p class="text-sm text-text-muted">{{ $quiz->section->title }}</p>
                <h2 class="text-xl font-semibold leading-snug">{{ $quiz->title }}</h2>

                <div class="flex flex-wrap items-center gap-1.5">
                    <x-badge :variant="match ($state) { 'open' => 'success', 'upcoming' => 'info', 'closed' => 'neutral', default => 'warning' }">{{ __('quizzes.state.'.$state) }}</x-badge>
                    @if ($quiz->category) <x-badge>{{ $quiz->category->name }}</x-badge> @endif
                    <x-badge>{{ __('quizzes.show.questions_count', ['n' => $questionCount]) }}</x-badge>
                    <x-badge>{{ __('quizzes.show.points', ['n' => $fmt($totalPoints)]) }}</x-badge>
                </div>
            </div>

            @if ($canManage)
                <div class="flex flex-wrap gap-2 sm:justify-end">
                    <x-button size="sm" icon="queue-list" :href="route('quizzes.questions', ['course' => $course, 'quiz' => $quiz])" wire:navigate>{{ __('quizzes.show.questions') }}</x-button>
                    <x-button variant="secondary" size="sm" icon="pencil-square" :href="route('quizzes.edit', ['course' => $course, 'quiz' => $quiz])" wire:navigate>{{ __('quizzes.show.edit') }}</x-button>
                    <x-button variant="secondary" size="sm" :icon="$quiz->is_published ? 'eye-slash' : 'eye'" wire:click="togglePublish">{{ $quiz->is_published ? __('quizzes.show.unpublish') : __('quizzes.show.publish') }}</x-button>
                    <x-button variant="ghost" size="sm" icon="trash" wire:click="deleteQuiz" wire:confirm="{{ __('quizzes.show.confirm_delete') }}">{{ __('quizzes.show.delete') }}</x-button>
                </div>
            @endif
        </div>

        <dl class="mt-5 grid gap-x-6 gap-y-3 border-t border-border pt-5 text-sm sm:grid-cols-2">
            <div><dt class="text-xs text-text-muted">{{ __('quizzes.show.opens') }}</dt><dd class="font-medium">{{ $opens ? $opens->translatedFormat('l, j F Y H:i') : __('quizzes.show.any_time') }}</dd></div>
            <div><dt class="text-xs text-text-muted">{{ __('quizzes.show.closes') }}</dt><dd class="font-medium">{{ $closes ? $closes->translatedFormat('l, j F Y H:i') : __('quizzes.show.any_time') }}</dd></div>
            <div><dt class="text-xs text-text-muted">{{ __('quizzes.form.duration') }}</dt><dd class="font-medium">{{ $quiz->duration_minutes ? __('quizzes.show.duration', ['n' => $quiz->duration_minutes]) : __('quizzes.show.no_duration') }}</dd></div>
            <div><dt class="text-xs text-text-muted">{{ __('quizzes.form.max_attempts') }}</dt><dd class="font-medium">{{ __('quizzes.show.attempts', ['n' => $quiz->max_attempts]) }}</dd></div>
        </dl>

        @if ($quiz->instructions)
            <div class="prose-content mt-5 border-t border-border pt-5" x-data="mathContent">{!! $quiz->instructions !!}</div>
        @endif
    </x-card>

    @if ($canSeeResults)
        <x-card :title="__('quizzes.results.title')">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm">
                    @if ($pendingGrading) <span class="font-medium text-info">{{ __('quizzes.show.pending', ['n' => $pendingGrading]) }}</span>
                    @else <span class="text-text-muted">{{ __('quizzes.results.empty') }}</span> @endif
                </p>
                <x-button variant="secondary" size="sm" icon="chart-bar" :href="route('quizzes.results', ['course' => $course, 'quiz' => $quiz])" wire:navigate>{{ __('quizzes.show.results') }}</x-button>
            </div>
            @if ($questionCount === 0 && $canManage)
                <p class="mt-3 rounded-lg bg-surface-muted p-3 text-sm text-text-muted">{{ __('quizzes.show.no_questions') }}</p>
            @endif
        </x-card>
    @endif

    @if ($canAttempt)
        <x-card :title="__('quizzes.show.rules')">
            @error('quiz') <p class="mb-3 rounded-lg border border-danger bg-danger/10 p-3 text-sm text-danger" role="alert">{{ $message }}</p> @enderror

            <ul class="mb-4 list-disc space-y-1 pl-5 text-sm text-text-muted">
                @if ($quiz->duration_minutes) <li>{{ __('quizzes.show.time_rule', ['n' => $quiz->duration_minutes]) }}</li> @endif
                <li>{{ __('quizzes.show.attempts_left', ['n' => $attemptsLeft]) }}</li>
                @if ($quiz->shuffle_questions) <li>{{ __('quizzes.show.shuffled') }}</li> @endif
                @if ($showAt && $showAt->isFuture()) <li>{{ __('quizzes.show.score_hidden', ['time' => $showAt->translatedFormat('j M Y H:i')]) }}</li> @endif
            </ul>

            @if ($running)
                <x-button icon="play" wire:click="start" wire:loading.attr="disabled" wire:target="start">{{ __('quizzes.show.resume') }}</x-button>
            @elseif ($attemptsLeft > 0 && $state === 'open' && $questionCount > 0)
                @if ($quiz->duration_minutes)
                    <x-button icon="play" wire:click="start" wire:loading.attr="disabled" wire:target="start" wire:confirm="{{ __('quizzes.show.confirm_start') }}">
                        {{ $attempts->isEmpty() ? __('quizzes.show.start') : __('quizzes.show.retry') }}
                    </x-button>
                @else
                    <x-button icon="play" wire:click="start" wire:loading.attr="disabled" wire:target="start">
                        {{ $attempts->isEmpty() ? __('quizzes.show.start') : __('quizzes.show.retry') }}
                    </x-button>
                @endif
            @else
                <p class="text-sm text-text-muted" role="status">{{ $questionCount === 0 ? __('quizzes.show.no_questions') : ($attemptsLeft === 0 ? __('quizzes.errors.no_attempts') : __('quizzes.show.not_open')) }}</p>
            @endif
        </x-card>

        @if ($attempts->isNotEmpty())
            <x-card :title="__('quizzes.show.history')" :padding="false">
                <ul class="divide-y divide-border">
                    @foreach ($attempts as $attempt)
                        <li class="flex flex-wrap items-center gap-3 px-4 py-3 text-sm sm:px-5" wire:key="attempt-{{ $attempt->id }}">
                            <span class="min-w-0 flex-1">
                                <span class="block font-medium">{{ __('quizzes.show.attempt_no', ['n' => $attempt->attempt_no]) }}</span>
                                <span class="block text-xs text-text-muted">{{ ($attempt->finished_at ?? $attempt->started_at)->timezone($tz)->translatedFormat('j M Y H:i') }} · {{ $attempt->status->label() }}</span>
                            </span>
                            @if ($attempt->isInProgress())
                                <x-badge variant="warning">{{ __('quizzes.show.in_progress') }}</x-badge>
                            @elseif ($attempt->status === \App\Enums\AttemptStatus::Graded && $quiz->resultsVisible())
                                <span class="font-semibold">{{ $fmt($attempt->score) }} <span class="text-xs font-normal text-text-muted">/ {{ $fmt($attempt->max_score) }}</span></span>
                            @elseif ($attempt->status === \App\Enums\AttemptStatus::Submitted)
                                <x-badge variant="info">{{ __('quizzes.show.awaiting') }}</x-badge>
                            @endif
                            @unless ($attempt->isInProgress())
                                <x-button variant="secondary" size="sm" :href="route('quizzes.review', ['course' => $course, 'quiz' => $quiz, 'attempt' => $attempt])" wire:navigate>{{ __('quizzes.show.view') }}</x-button>
                            @endunless
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif
    @endif
</div>
