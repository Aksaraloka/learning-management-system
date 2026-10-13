@php
    $user = auth()->user();
    $unread = $user->unreadNotifications()->count();
    $recent = $user->notifications()->limit(6)->get();
    $icons = ['assignment' => 'clipboard-document-list', 'quiz' => 'academic-cap', 'announcement' => 'megaphone', 'grade' => 'check-badge', 'reminder' => 'clock'];
@endphp

{{-- Lonceng notifikasi di header: lencana belum dibaca + 6 notifikasi terbaru; sisanya di halaman Notifikasi. --}}
<div x-data="{ open: false }" @keydown.escape="open = false" @click.outside="open = false" class="relative">
    <button type="button" @click="open = !open" :aria-expanded="open" aria-haspopup="true"
            aria-label="{{ __('ui.nav.notifications') }}@if ($unread) ({{ __('notifications.unread_count', ['n' => $unread]) }})@endif"
            class="relative inline-flex size-11 items-center justify-center rounded-lg hover:bg-surface-muted">
        <x-icon name="bell" />
        @if ($unread > 0)
            <span class="absolute right-1 top-1 grid min-w-4 place-items-center rounded-full bg-primary px-1 text-[10px] font-bold leading-4 text-primary-fg" aria-hidden="true">{{ $unread > 99 ? '99+' : $unread }}</span>
        @endif
    </button>

    <div x-show="open" x-cloak x-transition.opacity
         class="absolute right-0 z-40 mt-1 w-[min(22rem,calc(100vw-1.5rem))] overflow-hidden rounded-card border border-border bg-surface shadow-lg">
        <div class="flex items-center justify-between border-b border-border px-4 py-2.5">
            <h2 class="text-sm font-semibold">{{ __('ui.nav.notifications') }}</h2>
            @if ($unread > 0) <span class="text-xs text-text-muted">{{ __('notifications.unread_count', ['n' => $unread]) }}</span> @endif
        </div>

        @if ($recent->isEmpty())
            <p class="px-4 py-8 text-center text-sm text-text-muted">{{ __('notifications.empty') }}</p>
        @else
            <ul class="max-h-[60vh] divide-y divide-border overflow-y-auto">
                @foreach ($recent as $item)
                    <li>
                        <a href="{{ route('notifications.open', $item->id) }}" @class(['flex items-start gap-3 px-4 py-3 hover:bg-surface-muted', 'bg-primary/5' => $item->read_at === null])>
                            <span class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-full bg-primary/15 text-primary">
                                <x-icon :name="$icons[$item->data['kind'] ?? ''] ?? 'bell'" class="size-4" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span @class(['block text-sm leading-snug', 'font-semibold' => $item->read_at === null])>{{ $item->data['title'] }}</span>
                                @if ($item->data['body'] ?? null) <span class="block truncate text-xs text-text-muted">{{ $item->data['body'] }}</span> @endif
                                <span class="mt-0.5 block text-xs text-text-muted">{{ $item->created_at->timezone(config('app.timezone'))->diffForHumans() }}</span>
                            </span>
                            @if ($item->read_at === null) <span class="mt-2 size-2 shrink-0 rounded-full bg-primary" aria-hidden="true"></span> @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif

        <a href="{{ route('notifications.index') }}" wire:navigate class="block border-t border-border px-4 py-2.5 text-center text-sm font-medium text-primary hover:bg-surface-muted">{{ __('notifications.see_all') }}</a>
    </div>
</div>
