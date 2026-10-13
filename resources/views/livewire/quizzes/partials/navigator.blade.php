@props(['items', 'closeOnPick' => false])

{{-- Grid nomor soal: hijau = terjawab, kuning = ragu-ragu, garis tebal = soal aktif. Dipakai di dalam komponen quizAttempt. --}}
<div class="grid grid-cols-5 gap-2 sm:grid-cols-8 lg:grid-cols-5" role="list">
    @foreach ($items as $i => $item)
        <button type="button" role="listitem"
                @click="go({{ $i }}); @if ($closeOnPick ?? false) $dispatch('close-modal', 'quiz-navigator') @endif"
                class="relative grid size-10 place-items-center rounded-lg border text-sm font-semibold"
                :class="[
                    index === {{ $i }} ? 'ring-2 ring-primary' : '',
                    isFlagged({{ $item['id'] }}) ? 'border-warning bg-warning/15 text-warning' : (isAnswered({{ $item['id'] }}) ? 'border-success bg-success/15 text-success' : 'border-border text-text-muted'),
                ]"
                :aria-label="@js(__('quizzes.attempt.question_of', ['n' => $i + 1, 'total' => $items->count()])) + ', ' + (isFlagged({{ $item['id'] }}) ? @js(__('quizzes.attempt.flagged')) : (isAnswered({{ $item['id'] }}) ? @js(__('quizzes.attempt.answered')) : @js(__('quizzes.attempt.unanswered'))))"
                :aria-current="index === {{ $i }} ? 'true' : 'false'">
            {{ $i + 1 }}
        </button>
    @endforeach
</div>

<dl class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-text-muted">
    <div class="flex items-center gap-1.5"><dt class="size-3 rounded border border-success bg-success/15"></dt><dd>{{ __('quizzes.attempt.answered') }}</dd></div>
    <div class="flex items-center gap-1.5"><dt class="size-3 rounded border border-warning bg-warning/15"></dt><dd>{{ __('quizzes.attempt.flagged') }}</dd></div>
    <div class="flex items-center gap-1.5"><dt class="size-3 rounded border border-border"></dt><dd>{{ __('quizzes.attempt.unanswered') }}</dd></div>
</dl>
