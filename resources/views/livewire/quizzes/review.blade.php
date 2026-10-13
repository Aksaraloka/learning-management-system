@php
    use App\Enums\QuestionType;

    $tz = config('app.timezone');
    $fmt = fn ($n) => $n === null ? '–' : rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
    $showAt = $quiz->show_result_at?->timezone($tz);
@endphp

<div class="mx-auto max-w-3xl space-y-4">
    <a href="{{ $isStaff ? route('quizzes.results', ['course' => $course, 'quiz' => $quiz]) : route('quizzes.show', ['course' => $course, 'quiz' => $quiz]) }}" wire:navigate
       class="inline-flex min-h-11 items-center gap-1 text-sm text-primary">
        <x-icon name="arrow-left" class="size-4" /> {{ $isStaff ? __('quizzes.results.back') : __('quizzes.review.back') }}: {{ $quiz->title }}
    </a>

    <x-card>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold">{{ $isStaff && ! $isOwner ? $attempt->user->name : __('quizzes.review.title') }}</h2>
                <p class="text-sm text-text-muted">
                    @if ($isStaff && ! $isOwner) {{ $attempt->user->identity_number }} · @endif
                    {{ __('quizzes.review.attempt_of', ['n' => $attempt->attempt_no]) }} · {{ $attempt->finished_at?->timezone($tz)->translatedFormat('j M Y H:i') }}
                </p>
            </div>

            <div class="text-right">
                @if ($showScore)
                    <p class="text-xs text-text-muted">{{ __('quizzes.review.score') }}</p>
                    <p class="text-2xl font-semibold">{{ $fmt($attempt->score) }} <span class="text-sm font-normal text-text-muted">/ {{ $fmt($attempt->max_score) }}</span></p>
                @elseif ($pending && ($resultsVisible || $isStaff))
                    <x-badge variant="info">{{ $attempt->status->label() }}</x-badge>
                @endif
            </div>
        </div>

        @if (! $resultsVisible)
            <p class="mt-4 rounded-lg bg-surface-muted p-3 text-sm text-text-muted" role="status">{{ __('quizzes.review.hidden', ['time' => $showAt?->translatedFormat('j M Y H:i')]) }}</p>
        @elseif ($pending && ! $isStaff)
            <p class="mt-4 rounded-lg bg-surface-muted p-3 text-sm text-text-muted" role="status">{{ __('quizzes.review.pending') }}</p>
        @endif

        @if ($isStaff && $locked)
            <p class="mt-4 rounded-lg border border-warning bg-warning/10 p-3 text-sm" role="status"><x-icon name="lock-closed" class="mr-1 inline size-4" /> {{ __('quizzes.review.locked') }}</p>
        @endif

        @if ($resultsVisible && ! $isStaff && ! $rows->contains(fn ($r) => $r['correctText'] !== null) && $rows->contains(fn ($r) => $r['question']->type !== QuestionType::Essay))
            <p class="mt-4 text-xs text-text-muted">{{ __('quizzes.review.key_hidden') }}</p>
        @endif
    </x-card>

    @if ($resultsVisible || $isStaff)
        <form wire:submit="saveGrades" class="space-y-4">
            @foreach ($rows as $row)
                @php
                    $q = $row['question'];
                    $isEssay = $q->type === QuestionType::Essay;
                @endphp
                <x-card wire:key="row-{{ $q->id }}">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="flex items-center gap-2 text-sm font-semibold">
                            <span class="grid size-6 place-items-center rounded-full bg-surface-muted text-xs">{{ $row['number'] }}</span>
                            <span class="text-text-muted">{{ $q->type->label() }}</span>
                            @if ($row['flagged']) <x-badge variant="warning"><x-icon name="flag" class="size-3" /> {{ __('quizzes.review.flagged') }}</x-badge> @endif
                        </p>

                        <p class="flex items-center gap-2 text-sm">
                            @if ($isEssay && $row['manual'] === null)
                                <x-badge variant="info">{{ __('quizzes.review.pending_essay') }}</x-badge>
                            @elseif ($row['correct'] === true)
                                <x-badge variant="success"><x-icon name="check-circle" class="size-3.5" solid /> {{ __('quizzes.review.correct') }}</x-badge>
                            @elseif ($row['correct'] === false)
                                <x-badge variant="danger"><x-icon name="x-circle" class="size-3.5" solid /> {{ __('quizzes.review.wrong') }}</x-badge>
                            @endif
                            @if ($row['earned'] !== null)
                                <span class="font-semibold">{{ __('quizzes.review.earned', ['earned' => $fmt($row['earned']), 'max' => $fmt($q->points)]) }}</span>
                            @else
                                <span class="text-text-muted">{{ __('quizzes.questions.points', ['n' => $fmt($q->points)]) }}</span>
                            @endif
                        </p>
                    </div>

                    <div class="prose-content mt-3" x-data="mathContent">{!! $q->body !!}</div>
                    @if ($row['image']) <img src="{{ $row['image'] }}" alt="" loading="lazy" class="mt-3 max-h-72 rounded-card border border-border"> @endif

                    <div class="mt-4 space-y-2 text-sm">
                        <div class="rounded-lg border border-border p-3">
                            <p class="text-xs font-semibold text-text-muted">{{ $isOwner ? __('quizzes.review.your_answer') : __('quizzes.review.answer') }}</p>
                            @if ($row['answerText'] === null)
                                <p class="mt-1 text-text-muted">{{ __('quizzes.review.no_answer') }}</p>
                            @else
                                <p class="mt-1 whitespace-pre-line break-words">{{ $row['answerText'] }}</p>
                            @endif
                        </div>

                        @if ($row['correctText'])
                            <div class="rounded-lg border border-success/40 bg-success/10 p-3">
                                <p class="text-xs font-semibold text-success">{{ __('quizzes.review.correct_answer') }}</p>
                                <p class="mt-1 break-words">{{ $row['correctText'] }}</p>
                            </div>
                        @endif

                        @if ($row['rubric'])
                            <div class="rounded-lg bg-surface-muted p-3">
                                <p class="text-xs font-semibold text-text-muted">{{ __('quizzes.review.rubric') }}</p>
                                <p class="mt-1 whitespace-pre-line">{{ $row['rubric'] }}</p>
                            </div>
                        @endif

                        @if (! $canGrade && $isEssay && ($row['manual']['feedback'] ?? null))
                            <div class="rounded-lg bg-surface-muted p-3">
                                <p class="text-xs font-semibold text-text-muted">{{ __('quizzes.review.feedback') }}</p>
                                <p class="mt-1 whitespace-pre-line">{{ $row['manual']['feedback'] }}</p>
                            </div>
                        @endif
                    </div>

                    @if ($canGrade && $isEssay)
                        <div class="mt-4 grid gap-3 border-t border-border pt-4 sm:grid-cols-[8rem_1fr]">
                            <x-input name="scores.{{ $q->id }}" type="number" step="0.25" min="0" max="{{ $q->points }}" inputmode="decimal"
                                     wire:model="scores.{{ $q->id }}" :label="__('quizzes.review.essay_score').' (0–'.$fmt($q->points).')'" />
                            <x-textarea name="notes.{{ $q->id }}" wire:model="notes.{{ $q->id }}" rows="2" :label="__('quizzes.review.essay_feedback')" maxlength="2000" />
                        </div>
                    @endif
                </x-card>
            @endforeach

            @error('scores') <p class="text-sm text-danger" role="alert">{{ $message }}</p> @enderror

            @if ($canGrade && $rows->contains(fn ($r) => $r['question']->type === QuestionType::Essay))
                <div class="sticky bottom-3 z-10 flex justify-end">
                    <x-button type="submit" icon="check" wire:loading.attr="disabled" wire:target="saveGrades">{{ __('quizzes.review.save_grades') }}</x-button>
                </div>
            @endif
        </form>
    @endif
</div>
