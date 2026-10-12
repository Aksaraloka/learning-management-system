<div class="mx-auto max-w-2xl space-y-4">
    <a href="{{ $assignment ? route('assignments.show', ['course' => $course, 'assignment' => $assignment]) : route('courses.show', $course) }}" wire:navigate
       class="inline-flex min-h-11 items-center gap-1 text-sm text-primary">
        <x-icon name="arrow-left" class="size-4" /> {{ $assignment ? $assignment->title : $course->name }}
    </a>

    <x-card :title="$assignment ? __('assignments.form.edit') : __('assignments.form.create')">
        @if (! $sections)
            <p class="rounded-lg bg-surface-muted p-3 text-sm text-text-muted" role="alert">{{ __('courses.materials.no_sections') }}</p>
        @else
            <form wire:submit="save" class="space-y-4">
                <x-select name="section_id" wire:model="section_id" :label="term('section')" :placeholder="__('ui.actions.choose')" :options="$sections" required />
                <x-input name="title" wire:model="title" :label="__('assignments.form.title')" maxlength="255" required />
                <x-editor name="instructions" wire:model="instructions" :label="__('assignments.form.instructions')" :value="$instructions" />

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input.date name="due_at" wire:model="due_at" :value="$due_at" :label="__('assignments.form.due_at')" :hint="__('assignments.form.due_hint')" time clearable />
                    <x-input name="max_score" type="number" min="1" max="1000" wire:model="max_score" :label="__('assignments.form.max_score')" required />
                </div>

                <x-select name="grade_category_id" wire:model="grade_category_id" :label="__('assignments.form.category')" :placeholder="__('assignments.form.no_category')" :options="$categories" />

                <label class="flex min-h-11 items-center gap-3 text-sm">
                    <input type="checkbox" wire:model="allow_late" class="size-5 rounded border-border accent-[var(--color-primary)]">
                    {{ __('assignments.form.allow_late') }}
                </label>

                <div class="flex flex-wrap gap-2">
                    <x-button type="submit" icon="check" wire:loading.attr="disabled" wire:target="save">{{ __('assignments.form.save') }}</x-button>
                    <x-button variant="secondary" :href="$assignment ? route('assignments.show', ['course' => $course, 'assignment' => $assignment]) : route('courses.show', $course)" wire:navigate>{{ __('ui.actions.cancel') }}</x-button>
                </div>
            </form>
        @endif
    </x-card>

    <x-card :title="__('assignments.form.attachments')">
        @if (! $assignment)
            <p class="text-sm text-text-muted">{{ __('assignments.form.attach_after_save') }}</p>
        @else
            @if ($files->isNotEmpty())
                <ul class="mb-4 divide-y divide-border rounded-lg border border-border">
                    @foreach ($files as $file)
                        <li class="flex items-center gap-3 px-3 py-2" wire:key="file-{{ $file->id }}">
                            <x-icon name="paperclip" class="text-text-muted" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium">{{ $file->file_name }}</span>
                                <span class="block text-xs text-text-muted">{{ \App\Support\Upload::humanSize($file->file_size) }}</span>
                            </span>
                            <x-button variant="ghost" size="sm" icon="trash-2" wire:click="removeAttachment({{ $file->id }})" wire:confirm="{{ __('assignments.form.remove') }}?">
                                <span class="sr-only">{{ __('assignments.form.remove') }}</span>
                            </x-button>
                        </li>
                    @endforeach
                </ul>
            @endif

            <x-input.file name="upload" :label="__('courses.materials.file')" livewire-prop="upload" :max-size="$maxSize"
                          :hint="__('assignments.form.attach_help', ['max' => $maxLabel, 'types' => implode(', ', $mimes)])" />
        @endif
    </x-card>
</div>
