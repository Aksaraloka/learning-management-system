<div class="mx-auto max-w-2xl">
    <x-card :title="$course ? __('courses.form.edit') : __('courses.form.create')">
        @if (! $terms)
            <p class="rounded-lg bg-surface-muted p-3 text-sm text-text-muted" role="alert">{{ __('courses.form.no_terms') }}</p>
        @else
            <form wire:submit="save" class="space-y-4">
                @if ($managed)
                    <p class="rounded-lg bg-surface-muted p-3 text-sm text-text-muted" role="note">{{ __('courses.form.managed') }}</p>
                @endif

                <x-select name="academic_term_id" wire:model="academic_term_id" :label="__('courses.form.term')" :placeholder="__('courses.form.term_placeholder')" :options="$terms" :disabled="$managed" required />
                <x-input name="name" wire:model="name" :label="__('courses.form.name')" placeholder="Algoritma dan Pemrograman" :disabled="$managed" required />

                <x-input name="credits" type="number" min="1" max="24" wire:model="credits" :label="__('courses.form.credits')" :disabled="$managed" required />

                @if ($teachers)
                    <x-select name="teacher_id" wire:model="teacher_id" :label="term('teacher')" :hint="__('courses.form.teacher_hint')"
                              :placeholder="__('courses.form.teacher_placeholder')" :options="$teachers" required />
                @endif

                <x-textarea name="description" wire:model="description" :label="__('courses.form.description')" />

                <div class="space-y-2">
                    <p class="text-sm font-medium">{{ __('courses.form.banner') }}</p>
                    <img src="{{ $course ? $course->bannerUrl() : 'https://placehold.co/1200x400/9f1f1f/ffffff/png?text='.rawurlencode(__('courses.form.banner_preview')) }}"
                         alt="" referrerpolicy="no-referrer" class="aspect-[3/1] w-full rounded-card border border-border object-cover">
                    @if ($course?->hasBanner())
                        <label class="flex min-h-11 items-center gap-3 text-sm">
                            <input type="checkbox" wire:model="removeBanner" class="size-5 rounded border-border accent-[var(--color-primary)]">
                            {{ __('courses.form.banner_remove') }}
                        </label>
                    @endif
                    <x-input.file name="banner" livewire-prop="banner" max-size="2MB" :accept="['image/jpeg', 'image/png', 'image/webp']" :hint="__('courses.form.banner_hint')" />
                </div>

                <div class="flex flex-wrap gap-2">
                    <x-button type="submit" icon="check" wire:loading.attr="disabled" wire:target="save">{{ __('ui.actions.save') }}</x-button>
                    <x-button variant="secondary" :href="$course ? route('courses.show', $course) : route('courses.index')" wire:navigate>{{ __('ui.actions.cancel') }}</x-button>
                </div>
            </form>
        @endif
    </x-card>
</div>
