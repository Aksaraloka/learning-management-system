@php
    $tz = config('app.timezone');
@endphp

<div class="mx-auto max-w-3xl space-y-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="min-w-48 flex-1 sm:max-w-xs">
            <x-select name="scope" wire:model.live="scope" :label="__('announcements.filter')" :placeholder="__('announcements.all')">
                <option value="umum">{{ __('announcements.global') }}</option>
                @foreach ($scopes as $id => $name) <option value="{{ $id }}">{{ $name }}</option> @endforeach
            </x-select>
        </div>

        @if ($canPost && ! $showForm)
            <x-button icon="plus" wire:click="create">{{ __('announcements.add') }}</x-button>
        @endif
    </div>

    @if ($showForm)
        <x-card :title="$editingId ? __('announcements.edit') : __('announcements.add')">
            <form wire:submit="save" class="space-y-4">
                @unless ($editingId)
                    <x-select name="course_id" wire:model="course_id" :label="__('announcements.audience')" :placeholder="__('ui.actions.choose')">
                        @if ($isAdmin) <option value="umum">{{ __('announcements.global_all') }}</option> @endif
                        @foreach ($postable as $id => $name) <option value="{{ $id }}">{{ $name }}</option> @endforeach
                    </x-select>
                @endunless

                <x-input name="title" wire:model="title" :label="__('announcements.title')" maxlength="255" required />
                <x-editor name="body" wire:model="body" :label="__('announcements.body')" :value="$body" />

                <label class="flex min-h-11 items-center gap-3 text-sm">
                    <input type="checkbox" wire:model="is_pinned" class="size-5 rounded border-border accent-[var(--color-primary)]">
                    {{ __('announcements.pin') }}
                </label>

                <div class="flex flex-wrap gap-2">
                    <x-button type="submit" icon="check" wire:loading.attr="disabled" wire:target="save">{{ __('announcements.save') }}</x-button>
                    <x-button variant="secondary" wire:click="cancel">{{ __('ui.actions.cancel') }}</x-button>
                </div>
            </form>
        </x-card>
    @endif

    @if ($items->isEmpty())
        <x-card><x-empty-state icon="megaphone" :title="__('announcements.empty')" :description="$canPost ? __('announcements.empty_manager') : ''" /></x-card>
    @else
        <ul class="space-y-3">
            @foreach ($items as $item)
                <li wire:key="a-{{ $item->id }}">
                    <x-card>
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    @if ($item->is_pinned) <x-badge variant="warning"><x-icon name="bookmark" class="size-3" solid /> {{ __('announcements.pinned') }}</x-badge> @endif
                                    <x-badge :variant="$item->course ? 'neutral' : 'primary'">{{ $item->course?->name ?? __('announcements.global') }}</x-badge>
                                </div>
                                <h2 class="mt-1.5 text-base font-semibold leading-snug">{{ $item->title }}</h2>
                                <p class="text-xs text-text-muted">{{ $item->author->name }} · {{ $item->created_at->timezone($tz)->translatedFormat('j M Y H:i') }}</p>
                            </div>

                            @can('update', $item)
                                <div class="flex shrink-0 gap-1">
                                    <x-button variant="ghost" size="sm" icon="bookmark" wire:click="togglePin({{ $item->id }})"><span class="sr-only">{{ $item->is_pinned ? __('announcements.unpin') : __('announcements.pin') }}</span></x-button>
                                    <x-button variant="ghost" size="sm" icon="pencil-square" wire:click="edit({{ $item->id }})"><span class="sr-only">{{ __('announcements.edit') }}</span></x-button>
                                    <x-button variant="ghost" size="sm" icon="trash" wire:click="delete({{ $item->id }})" wire:confirm="{{ __('announcements.confirm_delete') }}"><span class="sr-only">{{ __('announcements.delete') }}</span></x-button>
                                </div>
                            @endcan
                        </div>

                        <div class="prose-content mt-3" x-data="mathContent">{!! $item->body !!}</div>
                    </x-card>
                </li>
            @endforeach
        </ul>

        <x-pagination :paginator="$items" />
    @endif
</div>
