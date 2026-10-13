@props(['done' => 0, 'total' => 0, 'percent' => 0, 'label' => null])

{{-- Bar kemajuan belajar per mata kuliah. Tanpa tugas/kuis, tampil keterangan kosong, bukan 0%. --}}
@if ($total === 0)
    <p {{ $attributes->class('text-xs text-text-muted') }}>{{ __('courses.progress.none') }}</p>
@else
    <div {{ $attributes }}>
        <div class="flex items-baseline justify-between gap-2 text-xs">
            <span class="font-medium text-text-muted">{{ $label ?? __('courses.progress.label') }}</span>
            <span class="font-semibold tabular-nums">{{ $percent }}% <span class="font-normal text-text-muted">({{ $done }}/{{ $total }})</span></span>
        </div>
        <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-surface-muted" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"
             aria-label="{{ __('courses.progress.aria', ['done' => $done, 'total' => $total]) }}">
            <div class="h-full rounded-full {{ $percent >= 100 ? 'bg-success' : 'bg-primary' }}" style="width: {{ $percent }}%"></div>
        </div>
    </div>
@endif
