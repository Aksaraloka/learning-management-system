@php
    $tz = config('app.timezone');
    $fmt = fn ($v) => is_float($v) || is_int($v) ? rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',') : (is_array($v) ? collect($v)->map(fn ($x, $k) => $k.' '.$x.'%')->join(', ') : (string) $v);
@endphp

<div class="space-y-4">
    <p class="text-sm text-text-muted">{{ __('audit.intro') }}</p>

    <div class="grid gap-3 sm:grid-cols-3">
        <x-select name="action" wire:model.live="action" :label="__('audit.action')" :placeholder="__('audit.all')">
            @foreach ($actions as $a) <option value="{{ $a }}">{{ \App\Models\AuditLog::labelFor($a) }}</option> @endforeach
        </x-select>
        <x-select name="course" wire:model.live="course" :label="__('audit.course')" :placeholder="__('audit.all')" :options="$courses" />
        <x-input name="search" type="search" wire:model.live.debounce.300ms="search" :label="__('audit.user')" />
    </div>

    @if ($logs->isEmpty())
        <x-card><x-empty-state icon="shield-check" :title="__('audit.empty')" /></x-card>
    @else
        <x-table>
            <thead>
                <tr>
                    <th>{{ __('audit.when') }}</th>
                    <th>{{ __('audit.user') }}</th>
                    <th>{{ __('audit.action') }}</th>
                    <th>{{ __('audit.detail') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($logs as $log)
                    @php
                        $meta = $log->meta ?? [];
                        $hasChange = array_key_exists('from', $meta) || array_key_exists('to', $meta);
                        $rest = collect($meta)->except(['from', 'to']);
                    @endphp
                    <tr wire:key="log-{{ $log->id }}">
                        <td data-label="{{ __('audit.when') }}" class="whitespace-nowrap">{{ $log->created_at->timezone($tz)->translatedFormat('j M Y H:i:s') }}</td>
                        <td data-label="{{ __('audit.user') }}">{{ $log->user?->name ?? __('audit.system') }}<span class="block text-xs text-text-muted">{{ $log->ip }}</span></td>
                        <td data-label="{{ __('audit.action') }}"><x-badge>{{ $log->label() }}</x-badge></td>
                        <td data-label="{{ __('audit.detail') }}" class="text-sm">
                            @if ($log->course_id) <span class="block text-xs text-text-muted">{{ $courseNames[$log->course_id] ?? '#'.$log->course_id }}</span> @endif
                            @if ($hasChange)
                                <span class="font-semibold tabular-nums">{{ isset($meta['from']) ? $fmt($meta['from']) : '–' }} → {{ isset($meta['to']) ? $fmt($meta['to']) : '–' }}</span>
                            @endif
                            @foreach ($rest as $key => $value)
                                <span class="block text-xs text-text-muted">{{ __('audit.keys.'.$key) === 'audit.keys.'.$key ? $key : __('audit.keys.'.$key) }}: {{ $fmt($value) }}</span>
                            @endforeach
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>

        <x-pagination :paginator="$logs" />
    @endif
</div>
