@php
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
@endphp

<div class="mx-auto max-w-3xl space-y-4">
    <a href="{{ route('quizzes.show', ['course' => $course, 'quiz' => $quiz]) }}" wire:navigate class="inline-flex min-h-11 items-center gap-1 text-sm text-primary">
        <x-icon name="arrow-left" class="size-4" /> {{ __('quizzes.questions.back') }}: {{ $quiz->title }}
    </a>

    <x-card :title="__('quizzes.questions.title')">
        <x-slot:actions>
            <x-badge>{{ __('quizzes.questions.total', ['n' => $fmt($totalPoints)]) }}</x-badge>
            <x-button size="sm" icon="plus" :href="route('quizzes.question.create', ['course' => $course, 'quiz' => $quiz])" wire:navigate>{{ __('quizzes.questions.add') }}</x-button>
        </x-slot:actions>

        @if ($hasAttempts)
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-warning bg-warning/10 p-3 text-sm" role="status">
                <span>{{ __('quizzes.questions.attempts_warning') }}</span>
                <x-button variant="secondary" size="sm" icon="arrow-path" wire:click="recompute" wire:loading.attr="disabled" wire:target="recompute">{{ __('quizzes.questions.recompute') }}</x-button>
            </div>
        @endif

        @if ($questions->isEmpty())
            <x-empty-state icon="queue-list" :title="__('quizzes.questions.empty')" />
        @else
            <x-sortable class="space-y-2" @sorted="$wire.reorder($event.detail.ids)">
                @foreach ($questions as $i => $question)
                    <li data-id="{{ $question->id }}" wire:key="question-{{ $question->id }}" class="flex items-start gap-2 rounded-lg border border-border bg-surface px-3 py-2">
                        <span data-handle class="mt-2 cursor-grab touch-none text-text-muted"><x-icon name="grip-vertical" class="size-4" /><span class="sr-only">{{ __('quizzes.questions.drag') }}</span></span>
                        <span class="mt-1.5 grid size-6 shrink-0 place-items-center rounded-full bg-surface-muted text-xs font-semibold">{{ $i + 1 }}</span>
                        <div class="min-w-0 flex-1 py-1">
                            <div class="line-clamp-2 text-sm [&_*]:inline" x-data="mathContent">{!! $question->body !!}</div>
                            <p class="mt-1 flex flex-wrap items-center gap-x-2 text-xs text-text-muted">
                                <x-icon :name="$question->type->icon()" class="size-3.5" /> {{ $question->type->label() }}
                                · {{ __('quizzes.questions.points', ['n' => $fmt($question->points)]) }}
                                @if ($question->hasImage()) · <x-icon name="photo" class="size-3.5" /> {{ __('quizzes.questions.has_image') }} @endif
                            </p>
                        </div>
                        <x-button variant="ghost" size="sm" icon="pencil-square" class="shrink-0" :href="route('quizzes.question.edit', ['course' => $course, 'quiz' => $quiz, 'question' => $question])" wire:navigate>
                            <span class="sr-only">{{ __('quizzes.questions.edit') }}</span>
                        </x-button>
                        <x-button variant="ghost" size="sm" icon="trash" class="shrink-0" wire:click="delete({{ $question->id }})" wire:confirm="{{ __('quizzes.questions.confirm_delete') }}">
                            <span class="sr-only">{{ __('quizzes.questions.delete') }}</span>
                        </x-button>
                    </li>
                @endforeach
            </x-sortable>
        @endif
    </x-card>
</div>
