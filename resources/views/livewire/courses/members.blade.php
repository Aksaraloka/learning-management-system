<div class="space-y-6">
    <a href="{{ route('courses.show', $course) }}" wire:navigate class="inline-flex min-h-11 items-center gap-1 text-sm text-primary">
        <x-icon name="arrow-left" class="size-4" /> {{ __('courses.members.back') }}
    </a>

    @if ($canManage)
        <x-card :title="__('courses.members.join_code')">
            <div class="flex flex-wrap items-center gap-3">
                <code class="rounded-lg bg-surface-muted px-3 py-1.5 text-lg font-semibold tracking-widest select-all">{{ $course->code }}</code>
                <x-button variant="secondary" size="sm" icon="refresh-cw" wire:click="regenerateCode" wire:confirm="{{ __('courses.members.confirm_regenerate') }}">{{ __('courses.members.regenerate') }}</x-button>
            </div>
        </x-card>

        <x-card :title="__('courses.members.add')">
            <form wire:submit="add" class="space-y-4">
                <x-select name="role" wire:model="role" :label="__('courses.members.role')" :options="['student' => term('student'), 'teacher' => term('teacher')]" />
                <x-textarea name="identifiers" wire:model="identifiers" :label="__('courses.members.identifiers')" :hint="__('courses.members.add_help')" rows="4" />
                <x-button type="submit" icon="user-plus" wire:loading.attr="disabled" wire:target="add">{{ __('courses.members.submit') }}</x-button>
            </form>

            @if ($result)
                <div class="mt-4 space-y-1 text-sm" role="status">
                    @if ($result['added']) <p class="text-success">{{ __('courses.members.added', ['n' => $result['added']]) }}</p> @endif
                    @if ($result['missing']) <p class="text-danger">{{ __('courses.members.missing', ['list' => implode(', ', $result['missing'])]) }}</p> @endif
                    @if ($result['ineligible']) <p class="text-warning">{{ __('courses.members.ineligible', ['list' => implode(', ', $result['ineligible'])]) }}</p> @endif
                    @if ($result['skipped']) <p class="text-text-muted">{{ __('courses.members.skipped', ['list' => implode(', ', $result['skipped'])]) }}</p> @endif
                </div>
            @endif
        </x-card>
    @endif

    @foreach (['teachers' => $teachers, 'students' => $students] as $key => $members)
        <x-card :title="__('courses.members.'.$key).' ('.$members->count().')'" :padding="false">
            @if ($members->isEmpty())
                <p class="p-4 text-sm text-text-muted">{{ __('courses.members.empty') }}</p>
            @else
                <ul class="divide-y divide-border">
                    @foreach ($members as $member)
                        <li class="flex items-center gap-3 px-4 py-2.5" wire:key="{{ $key }}-{{ $member->id }}">
                            <span class="grid size-8 shrink-0 place-items-center rounded-full bg-primary/15 text-sm font-semibold text-primary">{{ mb_strtoupper(mb_substr($member->name, 0, 1)) }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium">{{ $member->name }}</span>
                                <span class="block truncate text-xs text-text-muted">{{ $member->identity_number ?? $member->email }}</span>
                            </span>
                            @if ($canManage)
                                <x-button variant="ghost" size="sm" icon="user-minus" wire:click="remove({{ $member->id }})"
                                          wire:confirm="{{ __('courses.members.confirm_remove', ['name' => $member->name]) }}">
                                    <span class="sr-only">{{ __('courses.members.remove') }}</span>
                                </x-button>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    @endforeach
</div>
