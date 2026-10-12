@php
    $tz = config('app.timezone');
@endphp

<div class="space-y-4">
    <div class="grid gap-3 sm:max-w-3xl sm:grid-cols-3">
        <x-input name="search" type="search" wire:model.live.debounce.400ms="search" :label="__('ui.actions.search')" :placeholder="__('assignments.index.search')" />
        <x-select name="course" wire:model.live="course" :label="__('ui.nav.classes')" :placeholder="__('assignments.index.all_courses')" :options="$courses" />
        <x-select name="filter" wire:model.live="filter" :label="__('assignments.index.filter')" :placeholder="__('assignments.index.all')" :options="$filters" />
    </div>

    @if ($assignments->isEmpty())
        <x-card>
            <x-empty-state icon="clipboard-list" :title="__('assignments.index.empty_title')" :description="$isStudent ? __('assignments.index.empty_student') : __('assignments.index.empty_manager')" />
        </x-card>
    @else
        <ul class="space-y-3" x-reveal>
            @foreach ($assignments as $assignment)
                @php
                    $due = $assignment->due_at?->timezone($tz);
                    $mine = $isStudent ? $assignment->submissions->first() : null;
                    $status = \App\Models\Assignment::statusOf($mine);
                    $overdue = $assignment->isOverdue() && ($isStudent ? $status === \App\Enums\SubmissionStatus::NotSubmitted : false);
                @endphp
                <li wire:key="assignment-{{ $assignment->id }}">
                    <a href="{{ route('assignments.show', ['course' => $assignment->course_id, 'assignment' => $assignment]) }}" wire:navigate
                       class="flex flex-col gap-2 rounded-card border border-border bg-surface p-4 transition hover:border-primary sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0 space-y-1">
                            <p class="truncate font-semibold">{{ $assignment->title }}</p>
                            <p class="text-sm text-text-muted">{{ $assignment->course->name }}@if ($assignment->category) · {{ $assignment->category->name }}@endif</p>
                            <p class="flex items-center gap-1.5 text-xs {{ $overdue ? 'text-warning' : 'text-text-muted' }}">
                                <x-icon name="calendar-clock" class="size-3.5" />
                                @if ($due) {{ $due->translatedFormat('j M Y, H:i') }} · {{ $due->diffForHumans() }}@else {{ __('assignments.index.no_due') }}@endif
                            </p>
                        </div>

                        <div class="flex shrink-0 flex-wrap items-center gap-2 sm:justify-end">
                            @if ($isStudent)
                                <x-status-badge :status="$status->value" />
                                @if ($overdue && ! $assignment->allow_late) <x-badge variant="warning">{{ __('assignments.index.overdue') }}</x-badge> @endif
                                @if ($status === \App\Enums\SubmissionStatus::Graded) <span class="text-sm font-semibold">{{ rtrim(rtrim(number_format($mine->score, 2, ',', '.'), '0'), ',') }}/{{ $assignment->max_score }}</span> @endif
                            @else
                                <span class="text-sm text-text-muted">{{ __('assignments.index.submitted_of', ['done' => $assignment->submitted_count, 'total' => $studentCounts[$assignment->course_id] ?? 0]) }}</span>
                                @if ($assignment->pending_count) <x-badge variant="info">{{ __('assignments.index.pending', ['n' => $assignment->pending_count]) }}</x-badge> @endif
                            @endif
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>

        <x-pagination :paginator="$assignments" />
    @endif
</div>
