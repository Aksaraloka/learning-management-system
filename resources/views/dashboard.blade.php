<x-layouts.app :title="__('ui.nav.home')">
    <header class="flex flex-wrap items-end justify-between gap-x-4 gap-y-3">
        <div class="min-w-0">
            <p class="text-sm text-text-muted">{{ now()->timezone(config('app.timezone'))->translatedFormat('l, j F Y') }}</p>
            <h2 class="mt-0.5 text-2xl font-bold leading-tight sm:text-3xl">{{ __('ui.dashboard.welcome', ['name' => $user->name]) }}</h2>
            <div class="mt-2 flex flex-wrap gap-1.5">
                @foreach ($user->roles as $role)
                    <x-badge variant="primary">{{ \App\Enums\Role::tryFrom($role->name)?->label() ?? $role->name }}</x-badge>
                @endforeach
            </div>
        </div>

        <x-button variant="secondary" size="sm" icon="megaphone" :href="route('announcements.index')" wire:navigate>{{ __('announcements.title_page') }}</x-button>
    </header>

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
