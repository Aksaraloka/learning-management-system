<div class="space-y-6">
    @can('join', \App\Models\Course::class)
        <x-card :title="__('courses.join.title')">
            <form wire:submit="join" class="flex flex-col gap-3 sm:flex-row sm:items-start">
                <div class="flex-1">
                    <x-input name="code" wire:model="code" :label="__('courses.join.code')" :hint="__('courses.join.hint')"
                             autocomplete="off" autocapitalize="characters" maxlength="20" class="uppercase tracking-widest" />
                </div>
                <x-button type="submit" class="sm:mt-6" icon="user-plus" wire:loading.attr="disabled" wire:target="join">{{ __('courses.join.button') }}</x-button>
            </form>
        </x-card>
    @endcan

    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div class="grid flex-1 gap-3 sm:max-w-xl sm:grid-cols-2">
            <x-input name="search" type="search" wire:model.live.debounce.400ms="search" :label="__('ui.actions.search')" :placeholder="__('courses.index.search')" />
            <x-select name="term" wire:model.live="term" :label="__('courses.form.term')" :placeholder="__('courses.index.all_terms')" :options="$terms" />
        </div>
        @can('create', \App\Models\Course::class)
            <x-button :href="route('courses.create')" icon="plus" wire:navigate>{{ __('courses.index.create') }}</x-button>
        @endcan
    </div>

    @if ($courses->isEmpty())
        <x-card>
            <x-empty-state icon="book-open" :title="__('courses.index.empty_title')"
                           :description="auth()->user()->can('create', \App\Models\Course::class) ? __('courses.index.empty_manager') : __('courses.index.empty_student')" />
        </x-card>
    @else
        <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" x-reveal>
            @foreach ($courses as $course)
                <li wire:key="course-{{ $course->id }}">
                    <a href="{{ route('courses.show', $course) }}" wire:navigate
                       class="flex h-full flex-col gap-3 overflow-hidden rounded-card border border-border bg-surface pb-4 transition hover:border-primary">
                        <img src="{{ $course->bannerUrl() }}" alt="" loading="lazy" referrerpolicy="no-referrer" class="aspect-[3/1] w-full object-cover">
                        <div class="flex items-start justify-between gap-2 px-4">
                            <h2 class="font-semibold leading-snug">{{ $course->name }}</h2>
                            <x-icon name="chevron-right" class="mt-0.5 text-text-muted" />
                        </div>
                        <div class="flex flex-wrap gap-1.5 px-4">
                            <x-badge>{{ $course->term->name }}</x-badge>
                            @if ($course->credits) <x-badge variant="primary">{{ __('courses.index.credits', ['n' => $course->credits]) }}</x-badge> @endif
                        </div>
                        @if (isset($progress[$course->id]))
                            <x-progress :done="$progress[$course->id]['done']" :total="$progress[$course->id]['total']" :percent="$progress[$course->id]['percent']" class="px-4" />
                        @endif
                        <p class="mt-auto px-4 text-sm text-text-muted">
                            {{ $course->teachers->pluck('name')->join(', ') ?: __('courses.index.no_teacher') }}
                            · {{ __('courses.index.members_count', ['n' => $course->students_count]) }}
                        </p>
                    </a>
                </li>
            @endforeach
        </ul>

        <x-pagination :paginator="$courses" />
    @endif
</div>
