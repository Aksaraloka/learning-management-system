<div class="mx-auto max-w-3xl space-y-4">
    <a href="{{ route('attendance.index', $course) }}" wire:navigate class="inline-flex min-h-11 items-center gap-1 text-sm text-primary">
        <x-icon name="arrow-left" class="size-4" /> {{ __('attendance.title') }}: {{ $course->name }}
    </a>

    <x-card>
        <h2 class="text-lg font-semibold">{{ $session->held_on->translatedFormat('l, j F Y') }}@if ($session->title) · {{ $session->title }}@endif</h2>
        <div class="mt-2 flex flex-wrap gap-1.5">
            @foreach ($statusCases as $case)
                <x-badge :variant="$case->variant()">{{ $case->label() }}: {{ $counts[$case->value] ?? 0 }}</x-badge>
            @endforeach
        </div>
    </x-card>

    @if ($locked)
        <p class="rounded-lg border border-warning bg-warning/10 p-3 text-sm" role="status"><x-icon name="lock-closed" class="mr-1 inline size-4" /> {{ __('attendance.locked') }}</p>
    @elseif (! $canManage)
        <p class="rounded-lg bg-surface-muted p-3 text-sm text-text-muted" role="status">{{ __('attendance.view_only') }}</p>
    @endif

    @php $editable = $canManage && ! $locked; @endphp

    @if ($editable)
        <div class="flex flex-wrap gap-2">
            <x-button variant="secondary" size="sm" icon="check" wire:click="markAll('present')">{{ __('attendance.all_present') }}</x-button>
            <x-button variant="ghost" size="sm" wire:click="markAll('absent')">{{ __('attendance.all_absent') }}</x-button>
        </div>
    @endif

    <form wire:submit="save" class="space-y-3">
        @if ($students->isEmpty())
            <x-card><x-empty-state icon="users" /></x-card>
        @else
            <ul class="divide-y divide-border overflow-hidden rounded-card border border-border bg-surface">
                @foreach ($students as $student)
                    @php $current = $statuses[$student->id] ?? null; @endphp
                    <li class="space-y-2 px-4 py-3" wire:key="student-{{ $student->id }}">
                        <div class="flex items-baseline justify-between gap-2">
                            <span class="min-w-0 truncate text-sm font-medium">{{ $student->name }}</span>
                            <span class="shrink-0 text-xs text-text-muted">{{ $student->identity_number }}</span>
                        </div>

                        <div class="flex flex-wrap gap-1.5" role="radiogroup" aria-label="{{ $student->name }}">
                            @foreach ($statusCases as $case)
                                <button type="button" role="radio" aria-checked="{{ $current === $case->value ? 'true' : 'false' }}"
                                        @if ($editable) wire:click="setStatus({{ $student->id }}, '{{ $case->value }}')" @else disabled @endif
                                        @class([
                                            'min-h-10 min-w-16 rounded-lg border px-3 text-sm font-medium disabled:cursor-default',
                                            'border-primary bg-primary text-primary-fg' => $current === $case->value,
                                            'border-border text-text-muted hover:bg-surface-muted' => $current !== $case->value,
                                        ])>
                                    {{ $case->label() }}
                                </button>
                            @endforeach
                        </div>

                        @if ($editable)
                            <input type="text" wire:model="notes.{{ $student->id }}" class="input" maxlength="255" placeholder="{{ __('attendance.note') }}" aria-label="{{ __('attendance.note') }} {{ $student->name }}">
                        @elseif (! empty($notes[$student->id]))
                            <p class="text-xs text-text-muted">{{ $notes[$student->id] }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>

            @if ($editable)
                <div class="sticky bottom-20 z-10 flex justify-end lg:bottom-3">
                    <x-button type="submit" icon="check" wire:loading.attr="disabled" wire:target="save">{{ __('attendance.save') }}</x-button>
                </div>
            @endif
        @endif
    </form>
</div>
