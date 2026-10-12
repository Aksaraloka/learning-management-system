@php
    use App\Enums\SubmissionStatus;
    use App\Support\Upload;

    $tz = config('app.timezone');
    $sub = $current['submission'] ?? null;
@endphp

<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <a href="{{ route('assignments.show', ['course' => $course, 'assignment' => $assignment]) }}" wire:navigate class="inline-flex min-h-11 items-center gap-1 text-sm text-primary">
            <x-icon name="arrow-left" class="size-4" /> {{ __('assignments.grade.back') }}: {{ $assignment->title }}
        </a>
        <x-badge>{{ __('assignments.show.max_score', ['n' => $assignment->max_score]) }}</x-badge>
    </div>

    @if ($locked)
        <p class="rounded-lg border border-warning bg-warning/10 p-3 text-sm" role="status"><x-icon name="lock" class="mr-1 inline size-4" /> {{ __('assignments.grade.locked_notice') }}</p>
    @elseif (! $canGrade)
        <p class="rounded-lg bg-surface-muted p-3 text-sm text-text-muted" role="status">{{ __('assignments.grade.view_only') }}</p>
    @endif

    <div class="grid gap-4 lg:grid-cols-[20rem_1fr]">
        {{-- Daftar siswa: di HP tampil sampai siswa dipilih --}}
        <aside @class(['space-y-3', 'hidden lg:block' => $studentId]) aria-label="{{ __('assignments.grade.back_list') }}">
            <x-input name="search" type="search" wire:model.live.debounce.300ms="search" :label="__('assignments.grade.search')" />

            <div class="flex flex-wrap gap-1.5 text-xs" role="group">
                @foreach (['all' => 'filter_all', 'needs' => 'filter_needs', 'graded' => 'filter_graded', 'missing' => 'filter_missing'] as $key => $label)
                    <button type="button" wire:click="$set('filter', '{{ $key }}')" aria-pressed="{{ $filter === $key ? 'true' : 'false' }}"
                            @class(['min-h-9 rounded-full border px-3 font-medium', 'border-primary bg-primary text-primary-fg' => $filter === $key, 'border-border text-text-muted hover:bg-surface-muted' => $filter !== $key])>
                        {{ __('assignments.grade.'.$label, ['n' => $counts[$key]]) }}
                    </button>
                @endforeach
            </div>

            @if ($roster->isEmpty())
                <x-card><x-empty-state icon="users" /></x-card>
            @else
                <ul class="divide-y divide-border overflow-hidden rounded-card border border-border bg-surface">
                    @foreach ($roster as $row)
                        <li wire:key="row-{{ $row['student']->id }}">
                            <button type="button" wire:click="select({{ $row['student']->id }})" @if ($studentId === $row['student']->id) aria-current="true" @endif
                                    @class(['flex min-h-14 w-full items-center gap-3 px-3 py-2 text-left hover:bg-surface-muted', 'bg-primary/10' => $studentId === $row['student']->id])>
                                <span class="grid size-8 shrink-0 place-items-center rounded-full bg-primary/15 text-sm font-semibold text-primary">{{ mb_strtoupper(mb_substr($row['student']->name, 0, 1)) }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium">{{ $row['student']->name }}</span>
                                    <span class="block truncate text-xs text-text-muted">{{ $row['student']->identity_number }}</span>
                                </span>
                                <span class="flex shrink-0 flex-col items-end gap-1">
                                    <x-status-badge :status="$row['status']->value" />
                                    @if ($row['submission']?->score !== null)
                                        <span class="text-xs font-semibold">{{ rtrim(rtrim(number_format($row['submission']->score, 2, ',', '.'), '0'), ',') }}</span>
                                    @endif
                                </span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </aside>

        {{-- Pratinjau kiriman + nilai --}}
        <section @class(['space-y-4', 'hidden lg:block' => ! $studentId]) aria-live="polite">
            @if (! $current)
                <x-card><x-empty-state icon="list-checks" :title="__('assignments.grade.title')" :description="__('assignments.grade.select')" /></x-card>
            @else
                <button type="button" wire:click="select(null)" class="inline-flex min-h-11 items-center gap-1 text-sm text-primary lg:hidden">
                    <x-icon name="arrow-left" class="size-4" /> {{ __('assignments.grade.back_list') }}
                </button>

                <div wire:key="detail-{{ $current['student']->id }}" class="space-y-4">
                    <x-card>
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <h2 class="text-lg font-semibold">{{ $current['student']->name }}</h2>
                                <p class="text-sm text-text-muted">{{ $current['student']->identity_number }}</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-1.5">
                                <x-status-badge :status="$current['status']->value" />
                                @if ($sub?->is_late) <x-badge variant="warning">{{ __('assignments.grade.late_badge') }}</x-badge> @endif
                            </div>
                        </div>
                        @if ($sub?->submitted_at)
                            <p class="mt-1 text-xs text-text-muted">{{ __('assignments.submit.submitted_at', ['time' => $sub->submitted_at->timezone($tz)->translatedFormat('j M Y H:i')]) }}</p>
                        @endif
                    </x-card>

                    <x-card :title="__('assignments.grade.submission')">
                        @if (! $sub || ! $sub->hasContent())
                            <p class="text-sm text-text-muted">{{ __('assignments.grade.no_submission') }}</p>
                        @else
                            @if ($sub->body)
                                <div class="prose-content rounded-lg border border-border p-3" x-data="mathContent">{!! $sub->body !!}</div>
                            @endif

                            @if ($sub->file_path)
                                @php($fileUrl = route('submissions.file', $sub))
                                <div class="mt-4 space-y-3">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <x-button size="sm" variant="secondary" icon="download" :href="$fileUrl.'?download=1'">{{ __('assignments.grade.download') }}</x-button>
                                        <span class="text-sm text-text-muted">{{ $sub->file_name }} · {{ Upload::humanSize($sub->file_size) }}</span>
                                    </div>

                                    @if ($sub->isPdf())
                                        <x-pdf-viewer :url="$fileUrl" />
                                    @elseif ($sub->isImage())
                                        <img src="{{ $fileUrl }}" alt="{{ $current['student']->name }}" loading="lazy" class="mx-auto max-h-[60vh] rounded-card border border-border">
                                    @endif
                                </div>
                            @endif
                        @endif
                    </x-card>

                    <x-card :title="__('assignments.grade.score')">
                        <form wire:submit="save" class="space-y-4">
                            <div class="flex items-end gap-3">
                                <div class="w-32">
                                    <x-input name="score" type="number" step="0.01" min="0" max="{{ $assignment->max_score }}" inputmode="decimal"
                                             wire:model="score" :label="__('assignments.grade.score')" :disabled="! $canGrade" />
                                </div>
                                <p class="pb-3 text-sm text-text-muted">{{ __('assignments.grade.score_of', ['max' => $assignment->max_score]) }}</p>
                            </div>

                            <x-textarea name="feedback" wire:model="feedback" rows="3" :label="__('assignments.grade.feedback')" :disabled="! $canGrade" maxlength="5000" />

                            @if ($canGrade)
                                <div class="flex flex-wrap gap-2">
                                    <x-button type="submit" icon="check" wire:loading.attr="disabled" wire:target="save,saveAndNext">{{ __('assignments.grade.save') }}</x-button>
                                    <x-button variant="secondary" icon="chevron-right" wire:click="saveAndNext" wire:loading.attr="disabled" wire:target="save,saveAndNext">{{ __('assignments.grade.save_next') }}</x-button>
                                    @if ($sub && $sub->hasContent() && $current['status'] !== SubmissionStatus::Returned)
                                        <x-button variant="ghost" icon="refresh-cw" wire:click="returnForRevision" wire:confirm="{{ __('assignments.grade.confirm_return') }}">{{ __('assignments.grade.return') }}</x-button>
                                    @endif
                                </div>
                            @endif
                        </form>
                    </x-card>

                    <x-card :title="__('assignments.grade.private_thread')">
                        <p class="mb-3 text-xs text-text-muted">{{ __('assignments.grade.private_hint') }}</p>

                        @if ($thread->isNotEmpty())
                            <ul class="mb-4 space-y-3">
                                @foreach ($thread as $message)
                                    <li class="text-sm" wire:key="msg-{{ $message->id }}">
                                        <span class="font-medium">{{ $message->user->name }}</span>
                                        <span class="text-xs text-text-muted">· {{ $message->created_at->timezone($tz)->diffForHumans() }}</span>
                                        <p class="whitespace-pre-line break-words">{{ $message->body }}</p>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @can('update', $assignment)
                            <form wire:submit="sendReply" class="space-y-2">
                                <x-textarea name="reply" wire:model="reply" rows="2" :label="__('assignments.grade.reply')" :placeholder="__('assignments.grade.reply_placeholder')" maxlength="2000" />
                                <x-button type="submit" size="sm" icon="send" wire:loading.attr="disabled" wire:target="sendReply">{{ __('assignments.comments.send') }}</x-button>
                            </form>
                        @endcan
                    </x-card>
                </div>
            @endif
        </section>
    </div>
</div>
