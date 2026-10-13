<div class="mx-auto max-w-2xl space-y-4">
    <x-card :padding="false" class="overflow-hidden">
        <div class="h-24 bg-brand" aria-hidden="true"></div>
        <div class="px-4 pb-5 sm:px-6">
            <div class="-mt-12 flex items-end justify-between gap-3">
                @if ($user->avatarUrl())
                    <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}" class="size-24 rounded-full border-4 border-surface bg-surface object-cover">
                @else
                    <span class="grid size-24 place-items-center rounded-full border-4 border-surface bg-primary/15 text-4xl font-semibold text-primary" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                @endif

                <x-button size="sm" icon="pencil-square" :href="route('profile.edit')" wire:navigate>{{ __('ui.profile.edit_button') }}</x-button>
            </div>

            <h2 class="mt-3 text-2xl font-bold leading-tight">{{ $user->name }}</h2>
            <div class="mt-2 flex flex-wrap gap-1.5">
                @foreach ($user->roles as $role)
                    <x-badge variant="primary">{{ \App\Enums\Role::tryFrom($role->name)?->label() ?? $role->name }}</x-badge>
                @endforeach
            </div>

            @if ($user->bio)
                <p class="mt-4 whitespace-pre-line break-words text-sm">{{ $user->bio }}</p>
            @endif
        </div>
    </x-card>

    <x-card :title="__('ui.profile.info')">
        <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-text-muted">{{ term('identity') }}</dt>
                <dd class="mt-0.5 font-medium">{{ $user->identity_number ?: '–' }}</dd>
            </div>
            <div>
                <dt class="text-text-muted">Email</dt>
                <dd class="mt-0.5 break-all font-medium">{{ $user->email ?: '–' }}</dd>
            </div>
            <div>
                <dt class="text-text-muted">{{ __('ui.profile.phone') }}</dt>
                <dd class="mt-0.5 font-medium">{{ $user->phone ?: '–' }}</dd>
            </div>
            <div>
                <dt class="text-text-muted">{{ __('ui.profile.member_since') }}</dt>
                <dd class="mt-0.5 font-medium">{{ $user->created_at->timezone(config('app.timezone'))->translatedFormat('j F Y') }}</dd>
            </div>
        </dl>
    </x-card>

    @if ($courses->isNotEmpty())
        <x-card :title="__('courses.dashboard.my_courses')" :padding="false">
            <ul class="divide-y divide-border">
                @foreach ($courses as $course)
                    <li>
                        <a href="{{ route('courses.show', $course) }}" wire:navigate class="flex min-h-12 items-center gap-3 px-4 py-2.5 hover:bg-surface-muted sm:px-5">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium">{{ $course->name }}</span>
                                <span class="block truncate text-xs text-text-muted">{{ $course->term->name }}</span>
                            </span>
                            <x-icon name="chevron-right" class="size-4 text-text-muted" />
                        </a>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif
</div>
