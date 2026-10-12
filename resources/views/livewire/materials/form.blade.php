<div class="mx-auto max-w-2xl space-y-4">
    <a href="{{ route('courses.show', $course) }}" wire:navigate class="inline-flex min-h-11 items-center gap-1 text-sm text-primary">
        <x-icon name="arrow-left" class="size-4" /> {{ __('courses.materials.back') }}
    </a>

    <x-card :title="$material ? __('courses.materials.edit') : __('courses.materials.add')">
        @if (! $sections)
            <p class="rounded-lg bg-surface-muted p-3 text-sm text-text-muted" role="alert">{{ __('courses.materials.no_sections') }}</p>
        @else
            <form wire:submit="save" class="space-y-4">
                <x-select name="section_id" wire:model="section_id" :label="term('section')" :placeholder="__('ui.actions.choose')" :options="$sections" required />

                <x-select name="type" wire:model.live="type" :label="__('courses.materials.type')"
                          :options="collect(\App\Enums\MaterialType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all()" />

                <x-input name="title" wire:model="title" :label="__('courses.materials.title')" maxlength="255" required />

                @if ($type === 'text')
                    <x-editor name="body" wire:model="body" :label="__('courses.materials.body')" :value="$body" />
                @elseif ($type === 'file')
                    @if ($material?->file_name)
                        <p class="text-sm text-text-muted">{{ __('courses.materials.file_keep', ['name' => $material->file_name]) }}</p>
                    @endif
                    <x-input.file name="upload" :label="__('courses.materials.file')" livewire-prop="upload" :max-size="$maxSize"
                                  :hint="__('courses.materials.file_limit', ['max' => $maxLabel, 'types' => implode(', ', $mimes)])" />
                @else
                    <x-input name="url" type="url" wire:model="url" :label="__('courses.materials.url')" placeholder="https://" autocapitalize="none" required />
                @endif

                <div class="flex flex-wrap gap-2">
                    <x-button type="submit" icon="check" wire:loading.attr="disabled" wire:target="save,upload">{{ __('courses.materials.save') }}</x-button>
                    <x-button variant="secondary" :href="route('courses.show', $course)" wire:navigate>{{ __('ui.actions.cancel') }}</x-button>
                </div>
            </form>
        @endif
    </x-card>
</div>
