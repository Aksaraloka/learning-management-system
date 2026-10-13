<div class="mx-auto max-w-2xl space-y-4">
    <a href="{{ $quiz ? route('quizzes.show', ['course' => $course, 'quiz' => $quiz]) : route('courses.show', $course) }}" wire:navigate
       class="inline-flex min-h-11 items-center gap-1 text-sm text-primary">
        <x-icon name="arrow-left" class="size-4" /> {{ $quiz ? $quiz->title : $course->name }}
    </a>

    <x-card :title="$quiz ? __('quizzes.form.edit') : __('quizzes.form.create')">
        @if (! $sections)
            <p class="rounded-lg bg-surface-muted p-3 text-sm text-text-muted" role="alert">{{ __('courses.materials.no_sections') }}</p>
        @else
            <form wire:submit="save" class="space-y-4">
                <x-select name="section_id" wire:model="section_id" :label="term('section')" :placeholder="__('ui.actions.choose')" :options="$sections" required />
                <x-input name="title" wire:model="title" :label="__('quizzes.form.title')" maxlength="255" required />
                <x-editor name="instructions" wire:model="instructions" :label="__('quizzes.form.instructions')" :value="$instructions" />

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input.date name="opens_at" wire:model="opens_at" :value="$opens_at" :label="__('quizzes.form.opens_at')" :hint="__('quizzes.form.opens_hint')" time clearable />
                    <x-input.date name="closes_at" wire:model="closes_at" :value="$closes_at" :label="__('quizzes.form.closes_at')" :hint="__('quizzes.form.closes_hint')" time clearable />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input name="duration_minutes" type="number" min="1" max="600" inputmode="numeric" wire:model="duration_minutes" :label="__('quizzes.form.duration')" :hint="__('quizzes.form.duration_hint')" />
                    <x-input name="max_attempts" type="number" min="1" max="10" inputmode="numeric" wire:model="max_attempts" :label="__('quizzes.form.max_attempts')" required />
                </div>

                <x-input.date name="show_result_at" wire:model="show_result_at" :value="$show_result_at" :label="__('quizzes.form.show_result_at')" :hint="__('quizzes.form.show_hint')" time clearable />

                <x-select name="grade_category_id" wire:model="grade_category_id" :label="__('quizzes.form.category')" :placeholder="__('quizzes.form.no_category')" :options="$categories" />

                <div class="space-y-1">
                    <label class="flex min-h-11 items-center gap-3 text-sm">
                        <input type="checkbox" wire:model="shuffle_questions" class="size-5 rounded border-border accent-[var(--color-primary)]">
                        {{ __('quizzes.form.shuffle_questions') }}
                    </label>
                    <label class="flex min-h-11 items-center gap-3 text-sm">
                        <input type="checkbox" wire:model="shuffle_options" class="size-5 rounded border-border accent-[var(--color-primary)]">
                        {{ __('quizzes.form.shuffle_options') }}
                    </label>
                    <label class="flex min-h-11 items-start gap-3 py-2 text-sm">
                        <input type="checkbox" wire:model="is_published" class="mt-0.5 size-5 rounded border-border accent-[var(--color-primary)]">
                        <span>{{ __('quizzes.form.published') }}<span class="block text-xs text-text-muted">{{ __('quizzes.form.published_hint') }}</span></span>
                    </label>
                </div>

                <div class="flex flex-wrap gap-2">
                    <x-button type="submit" icon="check" wire:loading.attr="disabled" wire:target="save">{{ __('quizzes.form.save') }}</x-button>
                    <x-button variant="secondary" :href="$quiz ? route('quizzes.show', ['course' => $course, 'quiz' => $quiz]) : route('courses.show', $course)" wire:navigate>{{ __('ui.actions.cancel') }}</x-button>
                </div>
            </form>
        @endif
    </x-card>
</div>
