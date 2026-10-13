@php
    $config = [
        'attemptId' => $attempt->id,
        'ids' => $items->pluck('id')->all(),
        'answers' => $answers,
        'flags' => $flags,
        'remaining' => $remaining,
    ];
@endphp

{{-- Seluruh soal dimuat sekali; pindah soal terjadi di peramban. wire:ignore menjaga isian dari render ulang Livewire. --}}
<div wire:ignore x-data="quizAttempt(@js($config))"
     @online.window="online()" @offline.window="offline()" @visibilitychange.document="visible()"
     class="mx-auto max-w-4xl space-y-4 pb-24 lg:pb-0">

    <div class="sticky top-0 z-20 -mx-4 border-b border-border bg-bg/95 px-4 py-2 backdrop-blur sm:mx-0 sm:rounded-card sm:border sm:bg-surface sm:px-4">
        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold">{{ $quiz->title }}</p>
                <p class="text-xs text-text-muted" aria-live="polite">
                    <span x-text="@js(__('quizzes.attempt.question_of', ['n' => '__N__', 'total' => '__T__'])).replace('__N__', index + 1).replace('__T__', total)"></span>
                    ·
                    <span x-show="state === 'saved'">{{ __('quizzes.attempt.saved') }}</span>
                    <span x-show="state === 'saving'" x-cloak>{{ __('quizzes.attempt.saving') }}</span>
                    <span x-show="state === 'retrying'" x-cloak class="text-warning">{{ __('quizzes.attempt.retrying') }}</span>
                    <span x-show="state === 'offline'" x-cloak class="text-warning">{{ __('quizzes.attempt.offline') }}</span>
                </p>
            </div>

            <div class="flex shrink-0 items-center gap-2">
                <template x-if="left !== null">
                    <p class="flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-base font-semibold tabular-nums"
                       :class="left <= 60 ? 'bg-danger/15 text-danger' : 'bg-surface-muted'"
                       role="timer" :aria-label="@js(__('quizzes.attempt.time_left'))">
                        <x-icon name="clock" class="size-4" /> <span x-text="clock"></span>
                    </p>
                </template>
                <button type="button" class="inline-flex min-h-10 items-center rounded-lg bg-primary px-3 text-sm font-medium text-primary-fg hover:opacity-90"
                        @click="$dispatch('open-modal', 'finish-quiz')">{{ __('quizzes.attempt.finish') }}</button>
            </div>
        </div>
        <p x-show="left !== null && left <= 60 && left > 0" x-cloak class="mt-1 text-xs font-medium text-danger" role="alert">{{ __('quizzes.attempt.time_left') }}: <span x-text="clock"></span></p>
        <p x-show="expired" x-cloak class="mt-1 text-xs font-medium text-danger" role="alert">{{ __('quizzes.attempt.expired') }}</p>
    </div>

    <div class="grid gap-4 lg:grid-cols-[1fr_15rem]">
        <div class="min-w-0 space-y-4">
            @foreach ($items as $i => $item)
                <x-card x-show="index === {{ $i }}" :x-cloak="$i > 0" :aria-labelledby="'q-'.$item['id']">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p id="q-{{ $item['id'] }}" class="text-sm font-semibold">{{ __('quizzes.attempt.question_of', ['n' => $i + 1, 'total' => $items->count()]) }}</p>
                        <span class="flex items-center gap-2 text-xs text-text-muted">
                            {{ \App\Enums\QuestionType::from($item['type'])->label() }} · {{ __('quizzes.attempt.points', ['n' => rtrim(rtrim(number_format($item['points'], 2, ',', '.'), '0'), ',')]) }}
                        </span>
                    </div>

                    <div class="prose-content mt-3" x-data="mathContent">{!! $item['body'] !!}</div>
                    @if ($item['image']) <img src="{{ $item['image'] }}" alt="" class="mt-3 max-h-80 rounded-card border border-border"> @endif

                    <div class="mt-5">
                        @if ($item['type'] === 'multiple_choice')
                            <div class="space-y-2" role="radiogroup" aria-labelledby="q-{{ $item['id'] }}">
                                @foreach ($item['options'] as $option)
                                    <label class="flex min-h-12 cursor-pointer items-start gap-3 rounded-lg border px-3 py-2.5 text-sm transition hover:bg-surface-muted"
                                           :class="answers[{{ $item['id'] }}] === @js($option['id']) ? 'border-primary bg-primary/10' : 'border-border'">
                                        <input type="radio" name="q{{ $item['id'] }}" class="mt-0.5 size-5 shrink-0 accent-[var(--color-primary)]"
                                               :checked="answers[{{ $item['id'] }}] === @js($option['id'])" @change="choose({{ $item['id'] }}, @js($option['id']))">
                                        <span class="min-w-0 break-words">{{ $option['text'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @elseif ($item['type'] === 'true_false')
                            <div class="grid grid-cols-2 gap-2" role="radiogroup" aria-labelledby="q-{{ $item['id'] }}">
                                @foreach (['true' => 'quizzes.question.true', 'false' => 'quizzes.question.false'] as $value => $label)
                                    <label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-lg border px-3 py-2.5 text-sm font-medium transition hover:bg-surface-muted"
                                           :class="answers[{{ $item['id'] }}] === '{{ $value }}' ? 'border-primary bg-primary/10' : 'border-border'">
                                        <input type="radio" name="q{{ $item['id'] }}" class="size-5 shrink-0 accent-[var(--color-primary)]"
                                               :checked="answers[{{ $item['id'] }}] === '{{ $value }}'" @change="choose({{ $item['id'] }}, '{{ $value }}')">
                                        {{ __($label) }}
                                    </label>
                                @endforeach
                            </div>
                        @elseif ($item['type'] === 'short_answer')
                            <input type="text" class="input" maxlength="500" autocomplete="off" aria-labelledby="q-{{ $item['id'] }}"
                                   placeholder="{{ __('quizzes.attempt.short_placeholder') }}"
                                   :value="answers[{{ $item['id'] }}] ?? ''" @input="type({{ $item['id'] }}, $event.target.value)">
                        @else
                            <textarea class="input min-h-40" rows="7" maxlength="20000" aria-labelledby="q-{{ $item['id'] }}"
                                      placeholder="{{ __('quizzes.attempt.answer_here') }}"
                                      :value="answers[{{ $item['id'] }}] ?? ''" @input="type({{ $item['id'] }}, $event.target.value)"></textarea>
                        @endif
                    </div>

                    <div class="mt-5 flex flex-wrap items-center justify-between gap-2 border-t border-border pt-4">
                        <button type="button" @click="toggleFlag({{ $item['id'] }})" class="inline-flex min-h-11 items-center gap-2 rounded-lg px-3 text-sm hover:bg-surface-muted"
                                :class="isFlagged({{ $item['id'] }}) ? 'text-warning font-semibold' : 'text-text-muted'" :aria-pressed="isFlagged({{ $item['id'] }}) ? 'true' : 'false'">
                            <x-icon name="flag" class="size-4" />
                            <span x-text="isFlagged({{ $item['id'] }}) ? @js(__('quizzes.attempt.unflag')) : @js(__('quizzes.attempt.flag'))"></span>
                        </button>
                    </div>
                </x-card>
            @endforeach

            {{-- Di HP berada tepat di atas navigasi bawah aplikasi (tinggi ±3,75rem). --}}
            <div class="fixed inset-x-0 bottom-[calc(3.75rem+env(safe-area-inset-bottom))] z-20 border-t border-border bg-bg/95 px-4 py-2 backdrop-blur lg:static lg:border-0 lg:bg-transparent lg:p-0">
                <div class="mx-auto flex max-w-4xl items-center justify-between gap-2">
                    <x-button variant="secondary" icon="chevron-left" x-on:click="prev()" x-bind:disabled="index === 0">{{ __('quizzes.attempt.previous') }}</x-button>
                    <button type="button" class="inline-flex min-h-11 items-center rounded-lg border border-border px-3 text-sm lg:hidden" @click="$dispatch('open-modal', 'quiz-navigator')">
                        <x-icon name="squares-2x2" class="mr-1.5 size-4" /> <span x-text="answeredCount + '/' + total"></span>
                    </button>
                    <x-button x-on:click="next()" x-show="index < total - 1">{{ __('quizzes.attempt.next') }} <x-icon name="chevron-right" class="size-4" /></x-button>
                    <x-button x-on:click="$dispatch('open-modal', 'finish-quiz')" x-show="index === total - 1" x-cloak>{{ __('quizzes.attempt.finish') }}</x-button>
                </div>
            </div>
        </div>

        {{-- Navigator nomor soal (desktop) --}}
        <aside class="hidden lg:block">
            <x-card :title="__('quizzes.attempt.navigator')" class="sticky top-24">
                @include('livewire.quizzes.partials.navigator', ['items' => $items])
            </x-card>
        </aside>
    </div>

    {{-- Navigator nomor soal (HP) --}}
    <x-modal name="quiz-navigator" :title="__('quizzes.attempt.navigator')">
        @include('livewire.quizzes.partials.navigator', ['items' => $items, 'closeOnPick' => true])
    </x-modal>

    <x-modal name="finish-quiz" :title="__('quizzes.attempt.confirm_title')">
        <div class="space-y-1 text-sm">
            <p x-show="answeredCount < total" class="font-medium text-warning" x-text="@js(__('quizzes.attempt.confirm_unanswered', ['n' => '__N__'])).replace('__N__', total - answeredCount)"></p>
            <p x-show="flaggedCount > 0" x-cloak class="font-medium text-warning" x-text="@js(__('quizzes.attempt.confirm_flagged', ['n' => '__N__'])).replace('__N__', flaggedCount)"></p>
            <p class="text-text-muted">{{ __('quizzes.attempt.confirm_ok') }}</p>
        </div>

        <x-slot:footer>
            <x-button variant="secondary" @click="$dispatch('close-modal', 'finish-quiz')">{{ __('quizzes.attempt.confirm_no') }}</x-button>
            <x-button icon="paper-airplane" @click="finishNow()">{{ __('quizzes.attempt.confirm_yes') }}</x-button>
        </x-slot:footer>
    </x-modal>
</div>
