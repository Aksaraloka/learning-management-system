<x-layouts.app :title="__('ui.nav.home')">
    <x-card>
        <h2 class="text-lg font-semibold">{{ __('ui.dashboard.welcome', ['name' => $user->name]) }}</h2>
        <div class="mt-2 flex flex-wrap gap-1">
            @foreach ($user->roles as $role)
                <x-badge variant="primary">{{ \App\Enums\Role::tryFrom($role->name)?->label() ?? $role->name }}</x-badge>
            @endforeach
        </div>
    </x-card>

    <a href="{{ route('announcements.index') }}" wire:navigate class="mt-4 flex min-h-12 items-center gap-3 rounded-card border border-border bg-surface px-4 hover:border-primary">
        <x-icon name="megaphone" class="text-primary" />
        <span class="flex-1 text-sm font-medium">{{ __('announcements.title_page') }}</span>
        <x-icon name="chevron-right" class="size-4 text-text-muted" />
    </a>

    @if ($kind === 'basic')
        <section class="mt-6 space-y-3" aria-labelledby="my-courses">
            <div class="flex items-center justify-between">
                <h2 id="my-courses" class="text-lg font-semibold">{{ __('courses.dashboard.my_courses') }}</h2>
                <a href="{{ route('courses.index') }}" class="text-sm text-primary underline">{{ __('courses.dashboard.all') }}</a>
            </div>

            @if ($courses->isEmpty())
                <x-card><x-empty-state icon="book-open" :title="__('courses.index.empty_title')" :description="__('courses.index.empty_student')" /></x-card>
            @else
                <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" x-reveal>
                    @foreach ($courses as $course)
                        <li>
                            <a href="{{ route('courses.show', $course) }}" class="flex h-full flex-col gap-2 rounded-card border border-border bg-surface p-4 hover:border-primary">
                                <span class="font-semibold leading-snug">{{ $course->name }}</span>
                                <span class="text-sm text-text-muted">{{ $course->teachers->pluck('name')->join(', ') ?: __('courses.index.no_teacher') }}</span>
                                <span class="mt-auto text-xs text-text-muted">{{ $course->term->name }} · {{ __('courses.index.members_count', ['n' => $course->students_count]) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @else
        @include('dashboard.'.$kind, ['data' => $data])
    @endif
</x-layouts.app>
