@php
    use App\Enums\SubmissionStatus;
    use App\Support\Upload;

    $tz = config('app.timezone');
    $due = $assignment->due_at?->timezone($tz);
@endphp

<div class="mx-auto max-w-3xl space-y-4">
    <a href="{{ route('courses.show', $course) }}" wire:navigate class="inline-flex min-h-11 items-center gap-1 text-sm text-primary">
        <x-icon name="arrow-left" class="size-4" /> {{ $course->name }}
    </a>

    <x-card>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-2">
                <p class="text-sm text-text-muted">{{ $assignment->section->title }}</p>
                <h2 class="text-xl font-semibold leading-snug">{{ $assignment->title }}</h2>

                <div class="flex flex-wrap items-center gap-1.5">
                    @if ($canSubmit) <x-status-badge :status="$status->value" /> @endif
                    @if ($assignment->category) <x-badge>{{ $assignment->category->name }}</x-badge> @endif
                    <x-badge>{{ __('assignments.show.max_score', ['n' => $assignment->max_score]) }}</x-badge>
                    <x-badge :variant="$assignment->allow_late ? 'neutral' : 'warning'">{{ $assignment->allow_late ? __('assignments.show.late_allowed') : __('assignments.show.late_closed') }}</x-badge>
                </div>

                <p class="flex items-center gap-2 text-sm {{ $assignment->isOverdue() ? 'text-warning' : 'text-text-muted' }}">
                    <x-icon name="calendar-clock" class="size-4" />
                    @if ($due)
                        {{ __('assignments.index.due') }} {{ $due->translatedFormat('l, j F Y H:i') }} · {{ $due->diffForHumans() }}
                    @else
                        {{ __('assignments.index.no_due') }}
                    @endif
                </p>
            </div>

            @if ($canManage)
                <div class="flex flex-wrap gap-2 sm:justify-end">
                    <x-button size="sm" icon="list-checks" :href="route('assignments.grade', ['course' => $course, 'assignment' => $assignment])" wire:navigate>{{ __('assignments.show.grade') }}</x-button>
                    <x-button variant="secondary" size="sm" icon="pencil" :href="route('assignments.edit', ['course' => $course, 'assignment' => $assignment])" wire:navigate>{{ __('assignments.show.edit') }}</x-button>
                    <x-button variant="secondary" size="sm" icon="copy" @click="$dispatch('open-modal', 'copy-assignment')">{{ __('assignments.show.copy') }}</x-button>
                    <x-button variant="ghost" size="sm" icon="trash-2" wire:click="deleteAssignment" wire:confirm="{{ __('assignments.show.confirm_delete') }}">{{ __('assignments.show.delete') }}</x-button>
                </div>
            @elseif ($canSeeSubmissions)
                <x-button variant="secondary" size="sm" icon="eye" :href="route('assignments.grade', ['course' => $course, 'assignment' => $assignment])" wire:navigate>{{ __('assignments.show.view_only') }}</x-button>
            @endif
        </div>

        @if ($assignment->instructions)
            <div class="prose-content mt-5 border-t border-border pt-5" x-data="mathContent">{!! $assignment->instructions !!}</div>
        @endif

        @if ($assignment->attachments->isNotEmpty())
            <div class="mt-5 border-t border-border pt-4">
                <h3 class="mb-2 text-sm font-semibold">{{ __('assignments.show.attachments') }}</h3>
                <ul class="divide-y divide-border rounded-lg border border-border">
                    @foreach ($assignment->attachments as $file)
                        <li class="flex items-center gap-3 px-3 py-2">
                            <x-icon name="paperclip" class="text-text-muted" />
                            <a href="{{ route('assignments.attachment', ['assignment' => $assignment, 'file' => $file]) }}{{ $file->isPreviewable() ? '' : '?download=1' }}"
                               target="_blank" rel="noopener" class="min-w-0 flex-1 truncate text-sm font-medium text-primary hover:underline">{{ $file->file_name }}</a>
                            <span class="shrink-0 text-xs text-text-muted">{{ Upload::humanSize($file->file_size) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </x-card>

    @if ($stats)
        <x-card :title="__('assignments.show.stats')">
            <p class="text-sm">{{ __('assignments.index.submitted_of', ['done' => $stats['submitted'], 'total' => $stats['students']]) }}
                @if ($stats['pending']) · <span class="font-medium text-info">{{ __('assignments.index.pending', ['n' => $stats['pending']]) }}</span> @endif</p>
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-surface-muted" role="progressbar" aria-valuenow="{{ $stats['submitted'] }}" aria-valuemin="0" aria-valuemax="{{ $stats['students'] }}">
                <div class="h-full rounded-full bg-primary" style="width: {{ $stats['students'] ? round($stats['submitted'] / $stats['students'] * 100) : 0 }}%"></div>
            </div>
        </x-card>
    @endif

    @if ($canSubmit)
        <x-card :title="__('assignments.submit.title')">
            @if ($mine)
                <div class="mb-4 space-y-2 text-sm">
                    @if ($mine->submitted_at)
                        <p class="text-text-muted">
                            {{ __('assignments.submit.submitted_at', ['time' => $mine->submitted_at->timezone($tz)->translatedFormat('j M Y H:i')]) }}
                            @if ($mine->is_late) · <span class="text-warning">{{ __('assignments.submit.late') }}</span> @endif
                        </p>
                    @endif

                    @if ($status === SubmissionStatus::Graded)
                        <p class="text-lg font-semibold">{{ __('assignments.submit.score') }}: {{ rtrim(rtrim(number_format($mine->score, 2, ',', '.'), '0'), ',') }} <span class="text-sm font-normal text-text-muted">/ {{ $assignment->max_score }}</span></p>
                    @endif

                    @if ($mine->feedback)
                        <div class="rounded-lg bg-surface-muted p-3">
                            <p class="text-xs font-semibold text-text-muted">{{ __('assignments.submit.feedback') }}</p>
                            <p class="mt-1 whitespace-pre-line">{{ $mine->feedback }}</p>
                        </div>
                    @endif

                    @if ($status === SubmissionStatus::Returned)
                        <p class="font-medium text-danger" role="status">{{ __('assignments.submit.revise') }}</p>
                    @endif

                    @if (! $open)
                        @if ($mine->body) <div class="prose-content rounded-lg border border-border p-3" x-data="mathContent">{!! $mine->body !!}</div> @endif
                        @if ($mine->file_path)
                            <a href="{{ route('submissions.file', $mine) }}?download=1" class="inline-flex items-center gap-2 text-primary hover:underline"><x-icon name="paperclip" class="size-4" /> {{ $mine->file_name }}</a>
                        @endif
                    @endif
                </div>
            @endif

            @if ($open)
                <form wire:submit="submit" class="space-y-4">
                    <x-editor name="body" wire:model="body" :label="__('assignments.submit.body')" :value="$body" />

                    @if ($mine?->file_name)
                        <p class="text-sm text-text-muted">{{ __('assignments.submit.file_current', ['name' => $mine->file_name]) }}</p>
                    @endif

                    <x-input.file name="upload" :label="__('assignments.submit.file')" livewire-prop="upload" :max-size="Upload::filepondSize()"
                                  :hint="__('assignments.submit.file_help', ['max' => Upload::label(), 'types' => implode(', ', config('courses.allowed_mimes'))])" />

                    <x-button type="submit" icon="send" wire:loading.attr="disabled" wire:target="submit,upload">
                        {{ $mine ? __('assignments.submit.button_replace') : __('assignments.submit.button') }}
                    </x-button>
                </form>
            @elseif ($status === SubmissionStatus::Graded)
                <p class="text-sm text-text-muted">{{ __('assignments.submit.graded_lock') }}</p>
            @elseif (! $mine)
                <p class="text-sm text-warning" role="status">{{ __('assignments.submit.closed') }}</p>
            @else
                <p class="text-sm text-text-muted">{{ __('assignments.submit.closed') }}</p>
            @endif
        </x-card>
    @endif

    <x-card :title="__('assignments.comments.title')">
        @if ($comments->isEmpty())
            <p class="text-sm text-text-muted">{{ __('assignments.comments.empty') }}</p>
        @else
            <ul class="space-y-3">
                @foreach ($comments as $comment)
                    <li class="flex gap-3" wire:key="comment-{{ $comment->id }}">
                        <span class="grid size-8 shrink-0 place-items-center rounded-full bg-primary/15 text-sm font-semibold text-primary">{{ mb_strtoupper(mb_substr($comment->user->name, 0, 1)) }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-x-2 text-sm">
                                <span class="font-medium">{{ $comment->user->name }}</span>
                                <span class="text-xs text-text-muted">{{ $comment->created_at->timezone($tz)->diffForHumans() }}</span>
                                @if ($comment->is_private) <x-badge variant="warning"><x-icon name="lock" class="size-3" /> {{ __('assignments.comments.private_badge') }}</x-badge> @endif
                            </p>
                            <p class="mt-0.5 whitespace-pre-line break-words text-sm">{{ $comment->body }}</p>
                        </div>
                        @can('delete', $comment)
                            <x-button variant="ghost" size="sm" icon="trash-2" wire:click="deleteComment({{ $comment->id }})" wire:confirm="{{ __('assignments.comments.delete') }}?">
                                <span class="sr-only">{{ __('assignments.comments.delete') }}</span>
                            </x-button>
                        @endcan
                    </li>
                @endforeach
            </ul>
        @endif

        <form wire:submit="postComment" class="mt-4 space-y-3 border-t border-border pt-4">
            <x-textarea name="commentBody" wire:model="commentBody" rows="2" :label="__('assignments.comments.label')" :placeholder="__('assignments.comments.placeholder')" maxlength="2000" />

            @if ($canSubmit)
                <fieldset class="flex flex-col gap-1 text-sm sm:flex-row sm:gap-6">
                    <legend class="sr-only">{{ __('assignments.comments.label') }}</legend>
                    <label class="flex min-h-11 items-center gap-2"><input type="radio" wire:model="commentPrivate" value="0" class="accent-[var(--color-primary)]"> {{ __('assignments.comments.public') }}</label>
                    <label class="flex min-h-11 items-center gap-2"><input type="radio" wire:model="commentPrivate" value="1" class="accent-[var(--color-primary)]"> {{ __('assignments.comments.private') }}</label>
                </fieldset>
            @endif

            <x-button type="submit" size="sm" icon="send" wire:loading.attr="disabled" wire:target="postComment">{{ __('assignments.comments.send') }}</x-button>
        </form>
    </x-card>

    @if ($canManage)
        <x-modal name="copy-assignment" :title="__('assignments.copy.title')">
            @if (! $copyable)
                <p class="text-sm text-text-muted">{{ __('assignments.copy.none') }}</p>
            @else
                <p class="mb-3 text-sm text-text-muted">{{ __('assignments.copy.help') }}</p>
                <form wire:submit="copy" id="copy-form" class="space-y-1">
                    @foreach ($copyable as $id => $name)
                        <label class="flex min-h-11 items-center gap-3 rounded-lg px-2 hover:bg-surface-muted">
                            <input type="checkbox" wire:model="copyTo" value="{{ $id }}" class="size-5 rounded accent-[var(--color-primary)]">
                            <span class="text-sm">{{ $name }}</span>
                        </label>
                    @endforeach
                </form>
            @endif

            <x-slot:footer>
                <x-button variant="secondary" @click="$dispatch('close-modal', 'copy-assignment')">{{ __('ui.actions.cancel') }}</x-button>
                @if ($copyable) <x-button type="submit" form="copy-form" icon="copy" wire:loading.attr="disabled" wire:target="copy">{{ __('assignments.copy.submit') }}</x-button> @endif
            </x-slot:footer>
        </x-modal>
    @endif
</div>
