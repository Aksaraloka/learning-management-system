@php
    use App\Enums\MaterialType;
    use App\Support\Upload;
    use App\Support\VideoEmbed;

    $fileUrl = route('materials.file', $material);
@endphp

<div class="mx-auto max-w-3xl space-y-4">
    <a href="{{ route('courses.show', $course) }}" wire:navigate class="inline-flex min-h-11 items-center gap-1 text-sm text-primary">
        <x-icon name="arrow-left" class="size-4" /> {{ $course->name }}
    </a>

    <x-card>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <p class="text-sm text-text-muted">{{ $material->section->title }}</p>
                <h2 class="text-xl font-semibold leading-snug">{{ $material->title }}</h2>
                <x-badge class="mt-2"><x-icon :name="$material->type->icon()" class="size-3" /> {{ $material->type->label() }}</x-badge>
            </div>

            @if ($canManage)
                <div class="flex flex-wrap gap-2">
                    <x-button variant="secondary" size="sm" icon="pencil" :href="route('materials.edit', ['course' => $course, 'material' => $material])" wire:navigate>{{ __('ui.actions.edit') }}</x-button>
                    <x-button variant="ghost" size="sm" icon="trash-2" wire:click="delete" wire:confirm="{{ __('courses.materials.confirm_delete') }}">{{ __('ui.actions.delete') }}</x-button>
                </div>
            @endif
        </div>

        <div class="mt-5">
            @switch($material->type)
                @case(MaterialType::Text)
                    <div class="prose-content" x-data="mathContent">{!! $material->body !!}</div>
                    @break

                @case(MaterialType::File)
                    <div class="space-y-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <x-button :href="$fileUrl.'?download=1'" icon="download">{{ __('courses.materials.download') }}</x-button>
                            <span x-data="offlineSave({ url: @js($fileUrl) })" x-show="supported" x-cloak data-done="{{ __('offline.save_done') }}" data-failed="{{ __('offline.save_failed') }}">
                                <x-button variant="secondary" icon="arrow-down-on-square" x-show="!saved" @click="save()" x-bind:disabled="busy">{{ __('offline.save') }}</x-button>
                                <x-button variant="ghost" icon="check-circle" x-show="saved" x-cloak @click="remove()">{{ __('offline.saved_remove') }}</x-button>
                            </span>
                            <span class="text-sm text-text-muted">{{ $material->file_name }} · {{ Upload::humanSize($material->file_size) }}</span>
                        </div>

                        @if ($material->isPdf())
                            <x-pdf-viewer :url="$fileUrl" />
                        @elseif ($material->isImage())
                            <img src="{{ $fileUrl }}" alt="{{ $material->title }}" loading="lazy" class="mx-auto max-h-[70vh] rounded-card border border-border">
                        @else
                            <p class="text-sm text-text-muted">{{ __('courses.materials.no_preview') }}</p>
                        @endif
                    </div>
                    @break

                @case(MaterialType::Video)
                    @php($embed = VideoEmbed::embedUrl((string) $material->url))
                    <div class="space-y-3">
                        @if ($embed)
                            {{-- Iframe baru dimuat setelah diputar agar hemat data. --}}
                            <div x-data="{ play: false }">
                                <button type="button" x-show="!play" @click="play = true"
                                        class="grid aspect-video w-full place-items-center rounded-card border border-border bg-surface-muted text-text-muted hover:text-primary">
                                    <span class="flex flex-col items-center gap-2"><x-icon name="play" class="size-10" /> {{ __('courses.materials.play') }}</span>
                                </button>
                                <template x-if="play">
                                    <iframe src="{{ $embed }}" title="{{ $material->title }}" class="aspect-video w-full rounded-card border border-border"
                                            allowfullscreen loading="lazy" referrerpolicy="strict-origin-when-cross-origin"
                                            sandbox="allow-scripts allow-same-origin allow-presentation allow-popups"></iframe>
                                </template>
                            </div>
                        @else
                            <p class="text-sm text-text-muted">{{ __('courses.materials.video_external') }}</p>
                        @endif
                        <x-button variant="secondary" :href="$material->url" icon="external-link" target="_blank" rel="noopener noreferrer">{{ __('courses.materials.open_link') }}</x-button>
                    </div>
                    @break

                @case(MaterialType::Link)
                    <x-button :href="$material->url" icon="external-link" target="_blank" rel="noopener noreferrer">{{ __('courses.materials.open_link') }}</x-button>
                    <p class="mt-2 break-all text-sm text-text-muted">{{ $material->url }}</p>
                    @break
            @endswitch
        </div>
    </x-card>
</div>
