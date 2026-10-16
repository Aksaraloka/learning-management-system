<div class="space-y-6">
    <x-card :padding="false" class="overflow-hidden">
        <img src="{{ $course->bannerUrl() }}" alt="{{ $course->name }}" referrerpolicy="no-referrer" class="aspect-[3/1] max-h-56 w-full object-cover">
        <div class="p-4 sm:p-5">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-2">
                <div class="flex flex-wrap gap-1.5">
                    <x-badge>{{ $course->term->name }}</x-badge>
                    @if ($course->credits) <x-badge variant="primary">{{ __('courses.index.credits', ['n' => $course->credits]) }}</x-badge> @endif
                    <x-badge>{{ __('courses.show.students_count', ['n' => $studentCount]) }}</x-badge>
                </div>
                <p class="text-sm text-text-muted">
                    {{ __('courses.show.teachers') }}: {{ $course->teachers->pluck('name')->join(', ') ?: '—' }}
                </p>
                @if ($course->description)
                    <p class="whitespace-pre-line text-sm">{{ $course->description }}</p>
                @endif
            </div>

            @if ($canManage)
                <div class="flex flex-wrap gap-2 sm:justify-end">
                    <x-button variant="secondary" size="sm" icon="pencil" :href="route('courses.edit', $course)" wire:navigate>{{ __('courses.show.edit') }}</x-button>
                    <x-button variant="secondary" size="sm" icon="users" :href="route('courses.members', $course)" wire:navigate>{{ __('courses.show.members') }}</x-button>
                    @can('delete', $course)
                        <x-button variant="ghost" size="sm" icon="trash-2" wire:click="deleteCourse" wire:confirm="{{ __('courses.show.confirm_delete') }}">{{ __('courses.show.delete') }}</x-button>
                    @endcan
                </div>
            @elseif ($canRoster)
                <x-button variant="secondary" size="sm" icon="users" :href="route('courses.members', $course)" wire:navigate>{{ __('courses.show.members') }}</x-button>
            @endif
        </div>

        @if ($progress)
            <x-progress class="mt-4 border-t border-border pt-4" :done="$progress['done']" :total="$progress['total']" :percent="$progress['percent']" />
        @endif

        <nav class="mt-4 flex flex-wrap gap-2 border-t border-border pt-4" aria-label="{{ __('courses.show.tools') }}">
            <x-button variant="secondary" size="sm" icon="table-cells" :href="route('gradebook.index', $course)" wire:navigate>{{ $canRoster ? __('gradebook.title') : __('gradebook.my_grade') }}</x-button>
            <x-button variant="secondary" size="sm" icon="calendar-days" :href="route('attendance.index', $course)" wire:navigate>{{ __('attendance.title') }}</x-button>
            <x-button variant="secondary" size="sm" icon="megaphone" :href="route('announcements.index', ['kelas' => $course->id])" wire:navigate>{{ __('announcements.title_page') }}</x-button>
        </nav>

        @if ($canManage && ! $course->isManagedByAcademic())
            <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-border pt-4" x-data="{ copied: false }">
                <span class="text-sm text-text-muted">{{ __('courses.show.join_code') }}</span>
                <code class="rounded-lg bg-surface-muted px-3 py-1.5 text-lg font-semibold tracking-widest select-all">{{ $course->code }}</code>
                <x-button variant="ghost" size="sm" icon="copy"
                          @click="navigator.clipboard?.writeText('{{ $course->code }}'); $dispatch('toast', { message: '{{ __('courses.show.copied') }}', type: 'success' })">{{ __('courses.show.copy') }}</x-button>
            </div>
        @endif
        </div>
    </x-card>

    <section class="space-y-3" aria-labelledby="materials-heading" x-data="sectionFold({ key: 'course-folds-{{ $course->id }}' })">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 id="materials-heading" class="text-lg font-semibold">{{ __('courses.show.materials') }}</h2>
            @if ($sections->isNotEmpty())
                <x-button variant="ghost" size="sm" icon="chevron-up-down" x-on:click="toggleAll()"><span data-fold-label="fold">{{ __('courses.show.fold_all') }}</span><span data-fold-label="unfold" hidden>{{ __('courses.show.unfold_all') }}</span></x-button>
            @endif
        </div>

        @if ($canManage)
            <form wire:submit="addSection" class="flex flex-col gap-2 sm:flex-row sm:items-start">
                <div class="flex-1">
                    <x-input name="newSectionTitle" wire:model="newSectionTitle" :label="term('section')" :placeholder="__('courses.sections.placeholder')" maxlength="255" />
                </div>
                <x-button type="submit" icon="plus" class="sm:mt-6" wire:loading.attr="disabled" wire:target="addSection">{{ __('courses.sections.add') }}</x-button>
            </form>
        @endif

        @if ($sections->isEmpty())
            <x-card>
                <x-empty-state icon="folder" :title="__('courses.show.empty_sections')" :description="$canManage ? __('courses.show.empty_sections_manager') : ''" />
            </x-card>
        @else
            <x-sortable handle="[data-section-handle]" class="space-y-4" x-reveal @sorted="$wire.reorderSections($event.detail.ids)">
                @foreach ($sections as $section)
                    <li data-id="{{ $section->id }}" data-fold-id="{{ $section->id }}" wire:key="section-{{ $section->id }}">
                        <x-card :padding="false">
                            <header class="flex items-center gap-2 border-b border-border px-3 py-2.5 sm:px-4" data-fold-header>
                                @if ($canManage)
                                    <span data-section-handle class="cursor-grab touch-none text-text-muted" title="{{ __('courses.sections.drag') }}">
                                        <x-icon name="grip-vertical" /><span class="sr-only">{{ __('courses.sections.drag') }}</span>
                                    </span>
                                @endif

                                @if ($renamingId === $section->id)
                                    <form wire:submit="saveRename" class="flex flex-1 flex-wrap items-center gap-2">
                                        <div class="min-w-40 flex-1">
                                            <x-input name="renamingTitle" wire:model="renamingTitle" :label="__('courses.sections.title')" class="[&>label]:sr-only" maxlength="255" autofocus />
                                        </div>
                                        <x-button type="submit" size="sm" icon="check">{{ __('ui.actions.save') }}</x-button>
                                        <x-button variant="ghost" size="sm" wire:click="cancelRename">{{ __('ui.actions.cancel') }}</x-button>
                                    </form>
                                @else
                                    <h3 class="min-w-0 flex-1 truncate font-semibold">{{ $section->title }}</h3>

                                    @if ($canManage)
                                        <x-button variant="ghost" size="sm" icon="plus" :href="route('materials.create', ['course' => $course, 'pertemuan' => $section->id])" wire:navigate>{{ __('courses.materials.add') }}</x-button>
                                        <x-dropdown :label="__('ui.actions.more')">
                                            <x-slot:trigger><x-icon name="ellipsis-horizontal" class="size-5" /></x-slot:trigger>
                                            <a href="{{ route('assignments.create', ['course' => $course, 'pertemuan' => $section->id]) }}" role="menuitem" wire:navigate class="flex min-h-10 w-full items-center gap-2 rounded-md px-3 text-sm hover:bg-surface-muted"><x-icon name="clipboard-list" class="size-4" /> {{ __('assignments.course.add') }}</a>
                                            <a href="{{ route('quizzes.create', ['course' => $course, 'pertemuan' => $section->id]) }}" role="menuitem" wire:navigate class="flex min-h-10 w-full items-center gap-2 rounded-md px-3 text-sm hover:bg-surface-muted"><x-icon name="academic-cap" class="size-4" /> {{ __('quizzes.course.add') }}</a>
                                            <button type="button" role="menuitem" wire:click="startRename({{ $section->id }})" class="flex min-h-10 w-full items-center gap-2 rounded-md px-3 text-sm hover:bg-surface-muted"><x-icon name="pencil" class="size-4" /> {{ __('courses.sections.rename') }}</button>
                                            <button type="button" role="menuitem" wire:click="deleteSection({{ $section->id }})" wire:confirm="{{ __('courses.sections.confirm_delete') }}" class="flex min-h-10 w-full items-center gap-2 rounded-md px-3 text-sm text-danger hover:bg-surface-muted"><x-icon name="trash-2" class="size-4" /> {{ __('courses.sections.delete') }}</button>
                                        </x-dropdown>
                                    @endif
                                @endif

                                {{-- Jumlah isi tetap terlihat saat pertemuan dilipat. --}}
                                <span class="shrink-0 text-xs text-text-muted" data-fold-count hidden>{{ __('courses.show.items_count', ['n' => $section->materials->count() + $section->assignments->count() + $section->quizzes->count()]) }}</span>
                                <button type="button" x-on:click="toggle({{ $section->id }})" data-fold-toggle aria-expanded="true"
                                        aria-label="{{ __('courses.show.toggle_section') }}: {{ $section->title }}"
                                        class="group grid size-9 shrink-0 place-items-center rounded-lg text-text-muted hover:bg-surface-muted">
                                    <x-icon name="chevron-down" class="size-5 transition-transform group-aria-[expanded=false]:-rotate-90" />
                                </button>
                            </header>

                            <div data-fold-body>
                            @if ($section->materials->isEmpty() && $section->assignments->isEmpty() && $section->quizzes->isEmpty())
                                <p class="px-4 py-4 text-sm text-text-muted">{{ __('courses.show.empty_section') }}</p>
                            @elseif ($section->materials->isNotEmpty())
                                <x-sortable class="space-y-0 divide-y divide-border" @sorted="$wire.reorderMaterials({{ $section->id }}, $event.detail.ids)">
                                    @foreach ($section->materials as $material)
                                        <li data-id="{{ $material->id }}" wire:key="material-{{ $material->id }}" class="flex items-center gap-2 px-3 sm:px-4">
                                            @if ($canManage)
                                                <span data-handle class="cursor-grab touch-none text-text-muted"><x-icon name="grip-vertical" class="size-4" /><span class="sr-only">{{ __('courses.sections.drag') }}</span></span>
                                            @endif
                                            <a href="{{ route('materials.show', ['course' => $course, 'material' => $material]) }}" wire:navigate
                                               @class(['flex min-h-12 min-w-0 flex-1 items-center gap-3 py-2 hover:text-primary', 'opacity-60' => $material->is_hidden])>
                                                <x-icon :name="$material->type->icon()" class="text-text-muted" />
                                                <span class="min-w-0 flex-1">
                                                    <span class="block truncate text-sm font-medium">{{ $material->title }}</span>
                                                    <span class="block text-xs text-text-muted">{{ $material->type->label() }}@if ($material->file_size) · {{ \App\Support\Upload::humanSize($material->file_size) }}@endif@if ($material->is_hidden) · <span class="font-semibold text-warning">{{ __('courses.visibility.hidden') }}</span>@endif</span>
                                                </span>
                                            </a>
                                            @if ($canManage)
                                                <div class="flex w-20 shrink-0 items-center">
                                                <x-button variant="ghost" size="sm" :icon="$material->is_hidden ? 'eye-slash' : 'eye'" class="shrink-0" wire:click="toggleVisibility('material', {{ $material->id }})">
                                                    <span class="sr-only">{{ $material->is_hidden ? __('courses.visibility.show') : __('courses.visibility.hide') }}</span>
                                                </x-button>
                                                <x-button variant="ghost" size="sm" icon="trash-2" class="shrink-0" wire:click="deleteMaterial({{ $material->id }})" wire:confirm="{{ __('courses.materials.confirm_delete') }}">
                                                    <span class="sr-only">{{ __('courses.materials.delete') }}</span>
                                                </x-button>
                                                </div>
                                            @endif
                                        </li>
                                    @endforeach
                                </x-sortable>
                            @endif

                            @if ($section->assignments->isNotEmpty())
                                <ul class="divide-y divide-border border-t border-border">
                                    @foreach ($section->assignments as $assignment)
                                        @php
                                            $due = $assignment->due_at?->timezone(config('app.timezone'));
                                            $mine = $isStudent ? $assignment->submissions->first() : null;
                                        @endphp
                                        <li wire:key="assignment-{{ $assignment->id }}" @class(['flex items-center', 'pr-3 sm:pr-4' => $canManage])>
                                            <a href="{{ route('assignments.show', ['course' => $course, 'assignment' => $assignment]) }}" wire:navigate
                                               @class(['flex min-h-12 min-w-0 flex-1 items-center gap-3 px-3 py-2 hover:text-primary sm:px-4', 'opacity-60' => $assignment->is_hidden])>
                                                <x-icon name="clipboard-list" class="text-primary" />
                                                <span class="min-w-0 flex-1">
                                                    <span class="block truncate text-sm font-medium">{{ $assignment->title }}</span>
                                                    <span class="block text-xs text-text-muted">{{ __('assignments.course.assignments') }} · {{ $due ? $due->translatedFormat('j M, H:i') : __('assignments.index.no_due') }}@if ($assignment->is_hidden) · <span class="font-semibold text-warning">{{ __('courses.visibility.hidden') }}</span>@endif</span>
                                                </span>
                                                @if ($isStudent)
                                                    <x-status-badge :status="\App\Models\Assignment::statusOf($mine)->value" />
                                                @elseif ($canRoster)
                                                    <span class="shrink-0 text-xs text-text-muted">{{ $assignment->submitted_count }}/{{ $studentCount }}@if ($assignment->pending_count) · <span class="font-semibold text-info">{{ __('assignments.index.pending', ['n' => $assignment->pending_count]) }}</span>@endif</span>
                                                @endif
                                            </a>
                                            @if ($canManage)
                                                <div class="flex w-20 shrink-0 items-center">
                                                <x-button variant="ghost" size="sm" :icon="$assignment->is_hidden ? 'eye-slash' : 'eye'" class="shrink-0" wire:click="toggleVisibility('assignment', {{ $assignment->id }})">
                                                    <span class="sr-only">{{ $assignment->is_hidden ? __('courses.visibility.show') : __('courses.visibility.hide') }}</span>
                                                </x-button>
                                            </div>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            @if ($section->quizzes->isNotEmpty())
                                <ul class="divide-y divide-border border-t border-border">
                                    @foreach ($section->quizzes as $quiz)
                                        @php
                                            $quizState = $quiz->state();
                                            $opens = $quiz->opens_at?->timezone(config('app.timezone'));
                                            $closes = $quiz->closes_at?->timezone(config('app.timezone'));
                                        @endphp
                                        <li wire:key="quiz-{{ $quiz->id }}" @class(['flex items-center', 'pr-3 sm:pr-4' => $canManage])>
                                            <a href="{{ route('quizzes.show', ['course' => $course, 'quiz' => $quiz]) }}" wire:navigate
                                               @class(['flex min-h-12 min-w-0 flex-1 items-center gap-3 px-3 py-2 hover:text-primary sm:px-4', 'opacity-60' => ! $quiz->is_published])>
                                                <x-icon name="academic-cap" class="text-primary" />
                                                <span class="min-w-0 flex-1">
                                                    <span class="block truncate text-sm font-medium">{{ $quiz->title }}</span>
                                                    <span class="block text-xs text-text-muted">
                                                        {{ __('quizzes.course.label') }} · {{ __('quizzes.course.questions', ['n' => $quiz->questions_count]) }}@if ($closes) · {{ __('quizzes.course.until') }} {{ $closes->translatedFormat('j M, H:i') }}@elseif ($opens && $quizState === 'upcoming') · {{ __('quizzes.course.from') }} {{ $opens->translatedFormat('j M, H:i') }}@endif
                                                    </span>
                                                </span>
                                                <x-badge :variant="match ($quizState) { 'open' => 'success', 'upcoming' => 'info', 'closed' => 'neutral', default => 'warning' }">{{ __('quizzes.state.'.$quizState) }}</x-badge>
                                            </a>
                                            @if ($canManage)
                                                <div class="flex w-20 shrink-0 items-center">
                                                <x-button variant="ghost" size="sm" :icon="$quiz->is_published ? 'eye' : 'eye-slash'" class="shrink-0" wire:click="toggleVisibility('quiz', {{ $quiz->id }})">
                                                    <span class="sr-only">{{ $quiz->is_published ? __('courses.visibility.hide') : __('courses.visibility.show') }}</span>
                                                </x-button>
                                            </div>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            </div>
                        </x-card>
                    </li>
                @endforeach
            </x-sortable>
        @endif
    </section>
</div>
