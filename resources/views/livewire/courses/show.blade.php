<div class="space-y-6">
    <x-card>
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
                    <x-button variant="ghost" size="sm" icon="trash-2" wire:click="deleteCourse" wire:confirm="{{ __('courses.show.confirm_delete') }}">{{ __('courses.show.delete') }}</x-button>
                </div>
            @elseif ($canRoster)
                <x-button variant="secondary" size="sm" icon="users" :href="route('courses.members', $course)" wire:navigate>{{ __('courses.show.members') }}</x-button>
            @endif
        </div>

        @if ($canManage)
            <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-border pt-4" x-data="{ copied: false }">
                <span class="text-sm text-text-muted">{{ __('courses.show.join_code') }}</span>
                <code class="rounded-lg bg-surface-muted px-3 py-1.5 text-lg font-semibold tracking-widest select-all">{{ $course->code }}</code>
                <x-button variant="ghost" size="sm" icon="copy"
                          @click="navigator.clipboard?.writeText('{{ $course->code }}'); $dispatch('toast', { message: '{{ __('courses.show.copied') }}', type: 'success' })">{{ __('courses.show.copy') }}</x-button>
            </div>
        @endif
    </x-card>

    <section class="space-y-3" aria-labelledby="materials-heading">
        <h2 id="materials-heading" class="text-lg font-semibold">{{ __('courses.show.materials') }}</h2>

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
                    <li data-id="{{ $section->id }}" wire:key="section-{{ $section->id }}">
                        <x-card :padding="false">
                            <header class="flex items-center gap-2 border-b border-border px-3 py-2.5 sm:px-4">
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
                                        <x-button variant="ghost" size="sm" icon="plus" :href="route('materials.create', ['course' => $course, 'topik' => $section->id])" wire:navigate>{{ __('courses.materials.add') }}</x-button>
                                        <x-dropdown :label="__('ui.actions.more')">
                                            <x-slot:trigger><x-icon name="chevron-down" class="size-4" /></x-slot:trigger>
                                            <button type="button" role="menuitem" wire:click="startRename({{ $section->id }})" class="flex min-h-10 w-full items-center gap-2 rounded-md px-3 text-sm hover:bg-surface-muted"><x-icon name="pencil" class="size-4" /> {{ __('courses.sections.rename') }}</button>
                                            <button type="button" role="menuitem" wire:click="deleteSection({{ $section->id }})" wire:confirm="{{ __('courses.sections.confirm_delete') }}" class="flex min-h-10 w-full items-center gap-2 rounded-md px-3 text-sm text-danger hover:bg-surface-muted"><x-icon name="trash-2" class="size-4" /> {{ __('courses.sections.delete') }}</button>
                                        </x-dropdown>
                                    @endif
                                @endif
                            </header>

                            @if ($section->materials->isEmpty())
                                <p class="px-4 py-4 text-sm text-text-muted">{{ __('courses.show.empty_section') }}</p>
                            @else
                                <x-sortable class="space-y-0 divide-y divide-border" @sorted="$wire.reorderMaterials({{ $section->id }}, $event.detail.ids)">
                                    @foreach ($section->materials as $material)
                                        <li data-id="{{ $material->id }}" wire:key="material-{{ $material->id }}" class="flex items-center gap-2 px-3 sm:px-4">
                                            @if ($canManage)
                                                <span data-handle class="cursor-grab touch-none text-text-muted"><x-icon name="grip-vertical" class="size-4" /><span class="sr-only">{{ __('courses.sections.drag') }}</span></span>
                                            @endif
                                            <a href="{{ route('materials.show', ['course' => $course, 'material' => $material]) }}" wire:navigate
                                               class="flex min-h-12 min-w-0 flex-1 items-center gap-3 py-2 hover:text-primary">
                                                <x-icon :name="$material->type->icon()" class="text-text-muted" />
                                                <span class="min-w-0 flex-1">
                                                    <span class="block truncate text-sm font-medium">{{ $material->title }}</span>
                                                    <span class="block text-xs text-text-muted">{{ $material->type->label() }}@if ($material->file_size) · {{ \App\Support\Upload::humanSize($material->file_size) }}@endif</span>
                                                </span>
                                            </a>
                                            @if ($canManage)
                                                <x-button variant="ghost" size="sm" icon="trash-2" class="shrink-0" wire:click="deleteMaterial({{ $material->id }})" wire:confirm="{{ __('courses.materials.confirm_delete') }}">
                                                    <span class="sr-only">{{ __('courses.materials.delete') }}</span>
                                                </x-button>
                                            @endif
                                        </li>
                                    @endforeach
                                </x-sortable>
                            @endif
                        </x-card>
                    </li>
                @endforeach
            </x-sortable>
        @endif
    </section>
</div>
