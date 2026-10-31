@php
    $me = auth()->user();
    $hour = (int) now()->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
    $firstName = \Illuminate\Support\Str::of($me->name)->explode(' ')->first();
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header title="My day" :description="$greeting.', '.$firstName.' · '.today()->format('l, F j')" />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container space-y-6">

            {{-- Summary --}}
            <div class="card grid grid-cols-3 divide-x divide-slate-100 overflow-hidden">
                <a href="{{ route('board.index') }}" class="stat-cell hover:bg-slate-50">
                    <p class="stat-label"><x-icon name="view-columns" variant="outline" class="h-4 w-4 max-sm:hidden text-sky-600" /><span class="truncate">Open tasks</span></p>
                    <p class="stat-value">{{ $tasks->count() }}</p>
                    <p class="stat-sub hidden sm:block">assigned to you</p>
                </a>
                <div @class(['stat-cell', 'bg-danger-50/50' => $overdueCount > 0])>
                    <p @class(['stat-label', 'text-danger-700' => $overdueCount > 0])>
                        <x-icon name="exclamation-circle" variant="outline" @class(['h-4 w-4 max-sm:hidden', 'text-danger-600' => $overdueCount > 0, 'text-slate-400' => $overdueCount === 0]) />
                        <span class="truncate">Overdue</span>
                    </p>
                    <p @class(['stat-value', 'text-danger-700' => $overdueCount > 0])>{{ $overdueCount }}</p>
                    <p @class(['stat-sub hidden sm:block', 'text-danger-700' => $overdueCount > 0])>{{ $overdueCount > 0 ? 'past their due date' : 'nothing late' }}</p>
                </div>
                <a href="{{ route('calendar.index') }}" class="stat-cell hover:bg-slate-50">
                    <p class="stat-label"><x-icon name="calendar" variant="outline" class="h-4 w-4 max-sm:hidden text-rose-600" /><span class="truncate">Meetings</span></p>
                    <p class="stat-value">{{ $meetings->count() }}</p>
                    <p class="stat-sub hidden sm:block">in the next two weeks</p>
                </a>
            </div>

            <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">

                {{-- ============ MY TASKS ============ --}}
                <section class="card lg:col-span-2" aria-labelledby="my-tasks-title">
                    <div class="card-header">
                        <div class="flex items-center gap-2">
                            <h2 id="my-tasks-title" class="card-title">My tasks</h2>
                            <span class="badge badge-slate">{{ $tasks->count() }}</span>
                        </div>
                        <a href="{{ route('board.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-brand-700 hover:text-brand-800">
                            Open board <x-icon name="arrow-right" variant="outline" class="h-4 w-4" />
                        </a>
                    </div>

                    @if ($tasks->isEmpty())
                        <x-empty-state icon="check" title="You're all caught up." description="No open tasks assigned to you." />
                    @else
                        <ul class="divide-y divide-slate-100">
                            @foreach ($tasks as $task)
                                @php
                                    $overdue = $task->due_date && $task->due_date->isPast() && ! $task->due_date->isToday();
                                    $dueToday = $task->due_date?->isToday();
                                @endphp
                                <li>
                                    <a href="{{ route('tasks.show', $task) }}" class="group flex flex-col gap-2 px-5 py-3.5 transition-colors hover:bg-slate-50/80 sm:flex-row sm:items-center sm:gap-4 sm:px-6">
                                        <div class="w-full min-w-0 flex-1">
                                            <p class="truncate text-sm font-medium text-slate-900 group-hover:text-brand-700">{{ $task->title }}</p>
                                            @if ($task->release)
                                                <p class="mt-1 flex items-center gap-1.5 text-xs text-slate-500">
                                                    <x-icon name="rocket" variant="outline" class="h-3.5 w-3.5 text-violet-500" />
                                                    <span class="truncate">{{ $task->release->name }}</span>
                                                </p>
                                            @endif
                                        </div>
                                        <div class="flex flex-none flex-wrap items-center gap-2">
                                            @if ($task->due_date)
                                                <span @class([
                                                    'badge',
                                                    'badge-danger' => $overdue,
                                                    'badge-warning' => $dueToday,
                                                    'badge-slate' => ! $overdue && ! $dueToday,
                                                ])>
                                                    {{ $overdue ? 'Overdue · ' : ($dueToday ? 'Due today · ' : 'Due ') }}{{ $task->due_date->format('M j') }}
                                                </span>
                                            @endif
                                            @include('partials.status-badge', ['status' => $task->status])
                                        </div>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                {{-- ============ SIDEBAR ============ --}}
                <div class="space-y-6">

                    {{-- Today's tasksheet --}}
                    <section class="card" aria-labelledby="sheet-title">
                        <div class="card-header">
                            <h2 id="sheet-title" class="card-title">Today's tasksheet</h2>
                            <a href="{{ route('tasksheet.user', $me) }}" class="inline-flex items-center gap-1 text-sm font-medium text-brand-700 hover:text-brand-800">
                                History <x-icon name="arrow-right" variant="outline" class="h-4 w-4" />
                            </a>
                        </div>
                        @if ($teams->isEmpty())
                            <p class="px-5 py-4 text-sm text-slate-500 sm:px-6">You're not on a team yet.</p>
                        @else
                            <ul class="divide-y divide-slate-100">
                                @foreach ($teams as $team)
                                    @php
                                        $entry = $sheetEntries->get($team->id);
                                        $sheetUrl = route('tasksheet.index', ['team' => $team->id]);
                                    @endphp
                                    <li class="flex items-center justify-between gap-3 px-5 py-3 sm:px-6">
                                        <span class="flex min-w-0 items-center gap-2.5 text-sm text-slate-700">
                                            <span class="h-2.5 w-2.5 flex-none rounded-full" style="background-color: {{ $team->color }}"></span>
                                            <span class="truncate">{{ $team->name }}</span>
                                            @if ($entry?->isHalfDay())
                                                <span class="badge badge-warning">Half day</span>
                                            @endif
                                        </span>
                                        @if ($entry?->isFullDayLeave())
                                            <a href="{{ $sheetUrl }}" class="badge badge-info flex-none hover:brightness-95">{{ $entry->leaveLabel() }}</a>
                                        @elseif ($entry?->isFullyFilled())
                                            <a href="{{ $sheetUrl }}" class="badge badge-success flex-none hover:brightness-95">Filled ✓</a>
                                        @elseif ($entry?->isPartiallyFilled())
                                            <a href="{{ $sheetUrl }}" title="Fill the remaining fields"
                                               class="badge badge-brand flex-none hover:brightness-95">Partially filled · {{ $entry->filledFieldCount() }}/{{ count(\App\Models\TasksheetEntry::TASK_FIELDS) }}</a>
                                        @else
                                            <a href="{{ $sheetUrl }}" class="badge badge-warning flex-none hover:brightness-95">Not filled — fill now</a>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>

                    {{-- Upcoming meetings --}}
                    <section class="card" aria-labelledby="meetings-title">
                        <div class="card-header">
                            <h2 id="meetings-title" class="card-title">Upcoming meetings</h2>
                            <a href="{{ route('calendar.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-brand-700 hover:text-brand-800">
                                Calendar <x-icon name="arrow-right" variant="outline" class="h-4 w-4" />
                            </a>
                        </div>
                        @if ($meetings->isEmpty())
                            <p class="px-5 py-4 text-sm text-slate-500 sm:px-6">No meetings in the next two weeks.</p>
                        @else
                            <ul class="divide-y divide-slate-100">
                                @foreach ($meetings as $meeting)
                                    @php $isToday = $meeting->starts_at->isToday(); @endphp
                                    <li>
                                        <a href="{{ route('events.show', $meeting) }}" class="group flex items-center gap-3.5 px-5 py-3 transition-colors hover:bg-slate-50/80 sm:px-6">
                                            <span @class([
                                                'flex h-11 w-11 flex-none flex-col items-center justify-center rounded-lg ring-1 ring-inset',
                                                'bg-brand-50 text-brand-700 ring-brand-600/15' => $isToday,
                                                'bg-white text-slate-700 ring-slate-200' => ! $isToday,
                                            ])>
                                                <span class="text-[9px] font-semibold uppercase leading-none tracking-wide">{{ $meeting->starts_at->format('M') }}</span>
                                                <span class="tabular mt-0.5 text-base font-semibold leading-none">{{ $meeting->starts_at->format('j') }}</span>
                                            </span>
                                            <span class="min-w-0">
                                                <span class="block truncate text-sm font-medium text-slate-900 group-hover:text-brand-700">{{ $meeting->title }}</span>
                                                <span class="mt-0.5 block text-xs text-slate-500">
                                                    {{ $isToday ? 'Today' : $meeting->starts_at->format('l') }} · {{ $meeting->timeLabel() }}
                                                </span>
                                            </span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
