@php
    $categories = $data['categories'];
    $items = collect($data['items']);
    $fmt = fn ($n) => $n === null ? '–' : rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
@endphp

<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <a href="{{ route('courses.show', $course) }}" wire:navigate class="inline-flex min-h-11 items-center gap-1 text-sm text-primary">
            <x-icon name="arrow-left" class="size-4" /> {{ $course->name }}
        </a>

        @if ($canRoster)
            <div class="flex flex-wrap gap-2">
                @if ($canManage)
                    <x-button variant="secondary" size="sm" icon="adjustments-horizontal" @click="$dispatch('open-modal', 'weights')">{{ __('gradebook.weights') }}</x-button>
                @endif
                <x-button variant="secondary" size="sm" icon="arrow-down-tray" :href="route('gradebook.export', $course)">{{ __('gradebook.export') }}</x-button>
            </div>
        @endif
    </div>

    @if ($attendanceUnavailable)
        <p class="rounded-lg border border-warning bg-warning/10 p-3 text-sm" role="status">{{ __('attendance.unavailable') }}</p>
    @endif

    <x-card :title="__('gradebook.weights')">
        <ul class="flex flex-wrap gap-2 text-sm">
            @foreach ($categories as $category)
                <li><x-badge :variant="$category->weight > 0 ? 'primary' : 'neutral'">{{ $category->name }}: {{ $category->weight }}%</x-badge></li>
            @endforeach
        </ul>
        @if ($data['weightTotal'] !== 100)
            <p class="mt-2 text-sm text-warning" role="status">{{ __('gradebook.weight_warning', ['n' => $data['weightTotal']]) }}</p>
        @endif
        <p class="mt-2 text-xs text-text-muted">{{ __('gradebook.rules') }}</p>
    </x-card>

    @if ($items->isEmpty())
        <x-card><x-empty-state icon="table-cells" :title="__('gradebook.empty')" :description="__('gradebook.empty_hint')" /></x-card>
    @elseif (! $canRoster)
        {{-- Mahasiswa: rincian nilai sendiri --}}
        @php
            $row = $data['rows'][auth()->id()] ?? null;
            $att = $attendance[auth()->id()] ?? null;
        @endphp
        <x-card :title="__('gradebook.my_grade')">
            <p class="text-3xl font-bold">{{ $fmt($row['final'] ?? null) }}</p>
            <p class="mt-1 text-sm text-text-muted">{{ __('gradebook.final') }} (0–100)</p>
            @if ($att) <p class="mt-2 text-sm">{{ __('gradebook.attendance') }}: <span class="font-semibold">{{ $fmt($att['percent']) }}%</span>
                · <a href="{{ route('attendance.index', $course) }}" wire:navigate class="text-primary hover:underline">{{ __('gradebook.attendance_detail') }}</a></p> @endif
        </x-card>

        @foreach ($categories as $category)
            @php $catItems = $items->where('category_id', $category->id); @endphp
            <x-card :title="$category->name.' · '.$category->weight.'%'" :padding="false">
                <x-slot:actions><span class="text-sm font-semibold">{{ $fmt($row['categories'][$category->id] ?? null) }}</span></x-slot:actions>
                @if ($catItems->isEmpty())
                    <p class="px-4 py-3 text-sm text-text-muted">{{ __('gradebook.no_items') }}</p>
                @else
                    <ul class="divide-y divide-border">
                        @foreach ($catItems as $item)
                            @php $cell = $row['items'][$item['key']] ?? null; @endphp
                            <li class="flex items-center gap-3 px-4 py-2.5 text-sm">
                                <x-icon :name="$item['type'] === 'quiz' ? 'academic-cap' : 'clipboard-document-list'" class="size-4 text-text-muted" />
                                <span class="min-w-0 flex-1 truncate">{{ $item['title'] }}</span>
                                @if ($cell['pending'] ?? false) <x-badge variant="info">{{ __('gradebook.pending') }}</x-badge>
                                @elseif (($cell['score'] ?? null) === null) <span class="text-text-muted">–</span>
                                @else <span class="font-semibold">{{ $fmt($cell['score']) }} <span class="text-xs font-normal text-text-muted">/ {{ $fmt($item['max']) }}</span></span> @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        @endforeach
    @else
        {{-- Pengelola / ketua program studi: tabel seluruh mahasiswa --}}
        <x-card :padding="false">
            <div class="overflow-x-auto">
                <table class="w-full min-w-max text-sm">
                    <thead class="border-b border-border bg-surface-muted text-left text-xs text-text-muted">
                        <tr>
                            <th class="sticky left-0 z-10 bg-surface-muted px-3 py-2">{{ __('gradebook.student') }}</th>
                            @foreach ($items as $item)
                                <th class="px-3 py-2 font-medium" title="{{ $item['title'] }}">
                                    <span class="block max-w-28 truncate">{{ $item['title'] }}</span>
                                    <span class="block font-normal">/ {{ $fmt($item['max']) }}</span>
                                </th>
                            @endforeach
                            @foreach ($categories as $category)
                                <th class="px-3 py-2 font-medium">{{ $category->name }}<span class="block font-normal">{{ $category->weight }}%</span></th>
                            @endforeach
                            <th class="px-3 py-2 font-semibold text-text">{{ __('gradebook.final') }}</th>
                            <th class="px-3 py-2 font-medium">{{ __('gradebook.attendance') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($data['students'] as $student)
                            @php $row = $data['rows'][$student->id]; @endphp
                            <tr wire:key="student-{{ $student->id }}">
                                <td class="sticky left-0 z-10 bg-surface px-3 py-2">
                                    <span class="block max-w-44 truncate font-medium">{{ $student->name }}</span>
                                    <span class="block text-xs text-text-muted">{{ $student->identity_number }}</span>
                                </td>
                                @foreach ($items as $item)
                                    @php $cell = $row['items'][$item['key']]; @endphp
                                    <td class="px-3 py-2 tabular-nums">
                                        @if ($cell['pending']) <x-badge variant="info">{{ __('gradebook.pending_short') }}</x-badge>
                                        @else {{ $fmt($cell['score']) }} @endif
                                    </td>
                                @endforeach
                                @foreach ($categories as $category)
                                    <td class="px-3 py-2 tabular-nums text-text-muted">{{ $fmt($row['categories'][$category->id]) }}</td>
                                @endforeach
                                <td class="px-3 py-2 font-semibold tabular-nums">{{ $fmt($row['final']) }}</td>
                                <td class="px-3 py-2 tabular-nums text-text-muted">{{ isset($attendance[$student->id]) ? $fmt($attendance[$student->id]['percent']).'%' : '–' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif

    @if ($canManage)
        <x-modal name="weights" :title="__('gradebook.weights')">
            @if ($locked)
                <p class="text-sm text-warning" role="status">{{ __('gradebook.locked') }}</p>
            @else
                <form wire:submit="saveWeights" id="weights-form" class="space-y-3">
                    @foreach ($entries as $id => $entry)
                        <div class="grid grid-cols-[1fr_6rem_auto] items-start gap-2" wire:key="entry-{{ $id }}">
                            <x-input name="entries.{{ $id }}.name" wire:model="entries.{{ $id }}.name" :label="__('gradebook.category')" class="[&>label]:sr-only" maxlength="100" />
                            <x-input name="entries.{{ $id }}.weight" type="number" min="0" max="100" inputmode="numeric" wire:model="entries.{{ $id }}.weight" :label="__('gradebook.weight')" class="[&>label]:sr-only" />
                            <x-button variant="ghost" size="sm" icon="trash" class="mt-1.5" wire:click="removeCategory({{ $id }})" wire:confirm="{{ __('gradebook.confirm_remove') }}"><span class="sr-only">{{ __('gradebook.remove') }}</span></x-button>
                        </div>
                    @endforeach
                    @error('weights') <p class="text-sm text-danger" role="alert">{{ $message }}</p> @enderror
                    <p class="text-xs text-text-muted">{{ __('gradebook.weight_hint') }}</p>
                </form>

                <form wire:submit="addCategory" class="mt-4 flex items-start gap-2 border-t border-border pt-4">
                    <div class="flex-1"><x-input name="newCategory" wire:model="newCategory" :label="__('gradebook.new_category')" maxlength="100" /></div>
                    <x-button type="submit" variant="secondary" icon="plus" class="mt-6">{{ __('gradebook.add') }}</x-button>
                </form>
            @endif

            <x-slot:footer>
                <x-button variant="secondary" @click="$dispatch('close-modal', 'weights')">{{ __('ui.actions.cancel') }}</x-button>
                @unless ($locked) <x-button type="submit" form="weights-form" icon="check" wire:loading.attr="disabled" wire:target="saveWeights">{{ __('gradebook.save') }}</x-button> @endunless
            </x-slot:footer>
        </x-modal>
    @endif
</div>
