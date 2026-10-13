@php
    $fmtPercent = fn ($p) => $p === null ? '–' : rtrim(rtrim(number_format($p, 1, ',', '.'), '0'), ',').'%';
@endphp

<div class="mx-auto max-w-3xl space-y-4">
    <a href="{{ route('courses.show', $course) }}" wire:navigate class="inline-flex min-h-11 items-center gap-1 text-sm text-primary">
        <x-icon name="arrow-left" class="size-4" /> {{ $course->name }}
    </a>

    @if ($locked)
        <p class="rounded-lg border border-warning bg-warning/10 p-3 text-sm" role="status"><x-icon name="lock-closed" class="mr-1 inline size-4" /> {{ __('attendance.locked') }}</p>
    @endif

    @if ($isStudent && $summary)
        <x-card :title="__('attendance.my_summary')">
            <p class="text-3xl font-bold">{{ $fmtPercent($summary['percent']) }}</p>
            <p class="mt-1 text-sm text-text-muted">{{ __('attendance.summary_line', ['present' => $summary['present'], 'total' => $summary['total']]) }}</p>
            <div class="mt-3 flex flex-wrap gap-1.5">
                @foreach (\App\Enums\AttendanceStatus::cases() as $case)
                    <x-badge :variant="$case->variant()">{{ $case->label() }}: {{ $summary[$case->value] }}</x-badge>
                @endforeach
            </div>
        </x-card>
    @endif

    @if ($canManage && ! $locked)
        <x-card :title="__('attendance.new_session')">
            <form wire:submit="createSession" class="grid gap-4 sm:grid-cols-2">
                <x-input.date name="held_on" wire:model="held_on" :value="$held_on" :label="__('attendance.date')" required />
                <x-select name="section_id" wire:model="section_id" :label="term('section')" :placeholder="__('attendance.no_section')" :options="$sections" />
                <div class="sm:col-span-2"><x-input name="title" wire:model="title" :label="__('attendance.session_title')" maxlength="255" /></div>
                <div class="sm:col-span-2"><x-button type="submit" icon="plus" wire:loading.attr="disabled" wire:target="createSession">{{ __('attendance.create') }}</x-button></div>
            </form>
        </x-card>
    @endif

    <x-card :title="__('attendance.title')" :padding="false">
        @if ($sessions->isEmpty())
            <x-empty-state icon="calendar-days" :title="__('attendance.empty')" :description="$canManage ? __('attendance.empty_manager') : ''" />
        @else
            <ul class="divide-y divide-border">
                @foreach ($sessions as $s)
                    @php
                        $record = $mine->get($s->id);
                    @endphp
                    <li class="flex flex-wrap items-center gap-3 px-4 py-3 sm:px-5" wire:key="session-{{ $s->id }}">
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-medium">{{ $s->held_on->translatedFormat('l, j F Y') }}@if ($s->title) · {{ $s->title }}@endif</span>
                            <span class="block text-xs text-text-muted">
                                @if ($s->section) {{ $s->section->title }} · @endif
                                @if (! $isStudent) {{ $s->records_count ? __('attendance.present_of', ['present' => $s->present_count, 'total' => $studentCount]) : __('attendance.not_taken') }} @endif
                            </span>
                        </span>

                        @if ($isStudent)
                            @if ($record) <x-badge :variant="$record->status->variant()">{{ $record->status->label() }}</x-badge>
                            @else <x-badge>{{ __('attendance.not_taken') }}</x-badge> @endif
                        @else
                            <x-button variant="secondary" size="sm" :href="route('attendance.session', ['course' => $course, 'session' => $s])" wire:navigate>{{ $canManage && ! $locked ? __('attendance.fill') : __('attendance.view') }}</x-button>
                            @if ($canManage && ! $locked)
                                <x-button variant="ghost" size="sm" icon="trash" wire:click="deleteSession({{ $s->id }})" wire:confirm="{{ __('attendance.confirm_delete') }}"><span class="sr-only">{{ __('attendance.delete') }}</span></x-button>
                            @endif
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
</div>
