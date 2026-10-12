<div class="mx-auto max-w-2xl">
    <x-card :title="$course ? __('courses.form.edit') : __('courses.form.create')">
        @if (! $terms)
            <p class="rounded-lg bg-surface-muted p-3 text-sm text-text-muted" role="alert">{{ __('courses.form.no_terms') }}</p>
        @else
            <form wire:submit="save" class="space-y-4">
                <x-select name="academic_term_id" wire:model="academic_term_id" :label="__('courses.form.term')" :placeholder="__('courses.form.term_placeholder')" :options="$terms" required />
                <x-input name="name" wire:model="name" :label="__('courses.form.name')" placeholder="{{ $campus ? 'Algoritma dan Pemrograman' : 'Matematika VII-A' }}" required />

                @if ($campus)
                    <x-input name="credits" type="number" min="1" max="24" wire:model="credits" :label="__('courses.form.credits')" required />
                @endif

                @if ($teachers)
                    <x-select name="teacher_id" wire:model="teacher_id" :label="term('teacher')" :hint="__('courses.form.teacher_hint')"
                              :placeholder="__('courses.form.teacher_placeholder')" :options="$teachers" required />
                @endif

                <x-textarea name="description" wire:model="description" :label="__('courses.form.description')" />

                <div class="flex flex-wrap gap-2">
                    <x-button type="submit" icon="check" wire:loading.attr="disabled" wire:target="save">{{ __('ui.actions.save') }}</x-button>
                    <x-button variant="secondary" :href="$course ? route('courses.show', $course) : route('courses.index')" wire:navigate>{{ __('ui.actions.cancel') }}</x-button>
                </div>
            </form>
        @endif
    </x-card>
</div>
