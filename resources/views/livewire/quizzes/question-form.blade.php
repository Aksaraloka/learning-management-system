<div class="mx-auto max-w-2xl space-y-4">
    <a href="{{ route('quizzes.questions', ['course' => $course, 'quiz' => $quiz]) }}" wire:navigate class="inline-flex min-h-11 items-center gap-1 text-sm text-primary">
        <x-icon name="arrow-left" class="size-4" /> {{ __('quizzes.questions.title') }}: {{ $quiz->title }}
    </a>

    <x-card :title="$question ? __('quizzes.question.edit') : __('quizzes.question.add')">
        <form wire:submit="save" class="space-y-4">
            @if ($hasAttempts)
                <p class="rounded-lg border border-warning bg-warning/10 p-3 text-sm" role="status">{{ __('quizzes.questions.attempts_warning') }}</p>
            @endif

            <div class="grid gap-4 sm:grid-cols-[1fr_8rem]">
                <x-select name="type" wire:model.live="type" :label="__('quizzes.question.type')" :options="$types" :disabled="$question && $hasAttempts" required />
                <x-input name="points" type="number" step="0.25" min="0.25" max="100" inputmode="decimal" wire:model="points" :label="__('quizzes.question.points')" required />
            </div>
            @if ($question && $hasAttempts)
                <p class="-mt-2 text-xs text-text-muted">{{ __('quizzes.question.locked_hint') }}</p>
            @endif

            <x-editor name="body" wire:model="body" :label="__('quizzes.question.body')" :value="$body" />

            @if ($question?->hasImage() && ! $removeImage)
                <div class="space-y-2">
                    <img src="{{ route('questions.image', $question) }}" alt="" class="max-h-64 rounded-card border border-border">
                    <label class="flex min-h-11 items-center gap-3 text-sm">
                        <input type="checkbox" wire:model="removeImage" class="size-5 rounded border-border accent-[var(--color-primary)]">
                        {{ __('quizzes.question.remove_image') }}
                    </label>
                </div>
            @endif

            <x-input.file name="upload" :label="__('quizzes.question.image')" livewire-prop="upload" :max-size="$maxSize" :accept="['image/jpeg', 'image/png', 'image/gif', 'image/webp']"
                          :hint="__('quizzes.question.image_hint', ['max' => $maxSize])" />

            @if ($type === 'multiple_choice')
                <fieldset class="space-y-2">
                    <legend class="mb-1 text-sm font-medium">{{ __('quizzes.question.options') }}</legend>
                    @foreach ($options as $i => $option)
                        <div class="flex items-start gap-2" wire:key="option-{{ $i }}">
                            <label class="mt-1 grid size-11 shrink-0 cursor-pointer place-items-center" title="{{ __('quizzes.question.correct') }}">
                                <input type="radio" wire:model="correctIndex" value="{{ $i }}" class="size-5 accent-[var(--color-primary)]">
                                <span class="sr-only">{{ __('quizzes.question.correct') }} {{ $i + 1 }}</span>
                            </label>
                            <div class="min-w-0 flex-1">
                                <x-input name="options.{{ $i }}" wire:model="options.{{ $i }}" :label="__('quizzes.question.option_n', ['n' => $i + 1])" class="[&>label]:sr-only" maxlength="500" />
                            </div>
                            @if (count($options) > 2)
                                <x-button variant="ghost" size="sm" icon="trash" class="mt-1.5 shrink-0" wire:click="removeOption({{ $i }})">
                                    <span class="sr-only">{{ __('quizzes.question.remove_option') }}</span>
                                </x-button>
                            @endif
                        </div>
                    @endforeach
                    @error('correctIndex') <p class="text-sm text-danger" role="alert">{{ $message }}</p> @enderror
                    @if (count($options) < $maxOptions)
                        <x-button variant="secondary" size="sm" icon="plus" wire:click="addOption">{{ __('quizzes.question.add_option') }}</x-button>
                    @endif
                </fieldset>
            @elseif ($type === 'true_false')
                <fieldset class="space-y-1">
                    <legend class="mb-1 text-sm font-medium">{{ __('quizzes.question.correct') }}</legend>
                    <label class="flex min-h-11 items-center gap-3 text-sm"><input type="radio" wire:model="trueFalse" value="true" class="size-5 accent-[var(--color-primary)]"> {{ __('quizzes.question.true') }}</label>
                    <label class="flex min-h-11 items-center gap-3 text-sm"><input type="radio" wire:model="trueFalse" value="false" class="size-5 accent-[var(--color-primary)]"> {{ __('quizzes.question.false') }}</label>
                </fieldset>
            @elseif ($type === 'short_answer')
                <x-textarea name="accepted" wire:model="accepted" rows="3" :label="__('quizzes.question.accepted')" :hint="__('quizzes.question.accepted_hint')" required />
                <label class="flex min-h-11 items-center gap-3 text-sm">
                    <input type="checkbox" wire:model="caseSensitive" class="size-5 rounded border-border accent-[var(--color-primary)]">
                    {{ __('quizzes.question.case_sensitive') }}
                </label>
            @else
                <x-textarea name="rubric" wire:model="rubric" rows="3" :label="__('quizzes.question.rubric')" :hint="__('quizzes.question.rubric_hint')" maxlength="2000" />
            @endif

            <div class="flex flex-wrap gap-2">
                <x-button type="submit" icon="check" wire:loading.attr="disabled" wire:target="save,upload">{{ __('quizzes.question.save') }}</x-button>
                <x-button variant="secondary" :href="route('quizzes.questions', ['course' => $course, 'quiz' => $quiz])" wire:navigate>{{ __('ui.actions.cancel') }}</x-button>
            </div>
        </form>
    </x-card>
</div>
