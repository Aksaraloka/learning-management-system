@php
    $icons = ['assignment' => 'clipboard-document-list', 'quiz' => 'academic-cap', 'announcement' => 'megaphone', 'grade' => 'check-badge', 'reminder' => 'clock'];
@endphp

<div class="mx-auto max-w-2xl space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex gap-1.5 text-xs" role="group">
            @foreach (['all' => 'filter_all', 'unread' => 'filter_unread'] as $key => $label)
                <button type="button" wire:click="$set('filter', '{{ $key }}')" aria-pressed="{{ $filter === $key ? 'true' : 'false' }}"
                        @class(['min-h-9 rounded-full border px-3 font-medium', 'border-primary bg-primary text-primary-fg' => $filter === $key, 'border-border text-text-muted hover:bg-surface-muted' => $filter !== $key])>
                    {{ __('notifications.'.$label) }}
                </button>
            @endforeach
        </div>

        @if ($unread)
            <x-button variant="ghost" size="sm" icon="check" wire:click="markAllRead">{{ __('notifications.mark_all') }}</x-button>
        @endif
    </div>

    @if ($notifications->isEmpty())
        <x-card><x-empty-state icon="bell" :title="__('notifications.empty')" :description="__('notifications.empty_hint')" /></x-card>
    @else
        <ul class="divide-y divide-border overflow-hidden rounded-card border border-border bg-surface">
            @foreach ($notifications as $item)
                <li wire:key="n-{{ $item->id }}">
                    <button type="button" wire:click="open('{{ $item->id }}')"
                            @class(['flex w-full items-start gap-3 px-4 py-3 text-left hover:bg-surface-muted', 'bg-primary/5' => $item->read_at === null])>
                        <span class="mt-0.5 grid size-9 shrink-0 place-items-center rounded-full bg-primary/15 text-primary">
                            <x-icon :name="$icons[$item->data['kind'] ?? ''] ?? 'bell'" class="size-5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span @class(['block text-sm', 'font-semibold' => $item->read_at === null])>{{ $item->data['title'] }}</span>
                            @if ($item->data['body'] ?? null) <span class="block text-xs text-text-muted">{{ $item->data['body'] }}</span> @endif
                            <span class="mt-0.5 block text-xs text-text-muted">{{ $item->created_at->timezone(config('app.timezone'))->diffForHumans() }}</span>
                        </span>
                        @if ($item->read_at === null)
                            <span class="mt-2 size-2.5 shrink-0 rounded-full bg-primary" aria-label="{{ __('notifications.unread') }}"></span>
                        @endif
                    </button>
                </li>
            @endforeach
        </ul>

        <x-pagination :paginator="$notifications" />
    @endif
</div>
