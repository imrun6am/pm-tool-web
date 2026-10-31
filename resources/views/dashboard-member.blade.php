<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="page-title">My day</h2>
            <span class="text-sm font-medium text-slate-600">{{ today()->format('l, M j, Y') }}</span>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container grid grid-cols-1 gap-6 lg:grid-cols-3">

            {{-- ============ MY TASKS ============ --}}
            <div class="lg:col-span-2">
                <div class="card">
                    <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                        <h3 class="text-sm font-semibold text-slate-700">My tasks ({{ $tasks->count() }})</h3>
                        <a href="{{ route('board.index') }}" class="text-xs font-medium text-brand-600 hover:text-brand-700">Open board →</a>
                    </div>
                    <div class="p-5 sm:p-6">
                        @if ($tasks->isEmpty())
                            <p class="text-sm text-slate-400">No open tasks assigned to you. 🎉</p>
                        @else
                            <ul class="divide-y divide-slate-100">
                                @foreach ($tasks as $task)
                                    @php $overdue = $task->due_date && $task->due_date->isPast() && ! $task->due_date->isToday(); @endphp
                                    <li class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                                        <div class="min-w-0">
                                            <a href="{{ route('tasks.show', $task) }}" class="block truncate text-sm font-medium text-slate-800 hover:text-brand-700">{{ $task->title }}</a>
                                            <p class="mt-0.5 truncate text-xs text-slate-400">{{ $task->release->name ?? '' }}</p>
                                        </div>
                                        <div class="flex flex-none items-center gap-2">
                                            @if ($task->due_date)
                                                <span @class([
                                                    'text-xs',
                                                    'font-semibold text-rose-600' => $overdue,
                                                    'text-slate-500' => ! $overdue,
                                                ])>
                                                    {{ $overdue ? 'Overdue · ' : 'Due ' }}{{ $task->due_date->format('M j') }}
                                                </span>
                                            @endif
                                            @include('partials.status-badge', ['status' => $task->status])
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ============ SIDEBAR ============ --}}
            <div class="space-y-6 lg:col-span-1">

                {{-- Today's tasksheet --}}
                <div class="card card-pad">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-slate-700">Today's tasksheet</h3>
                        <a href="{{ route('tasksheet.user', auth()->user()) }}" class="text-xs font-medium text-brand-600 hover:text-brand-700">History →</a>
                    </div>
                    @if ($teams->isEmpty())
                        <p class="mt-2 text-sm text-slate-400">You're not on a team yet.</p>
                    @else
                        <ul class="mt-3 space-y-2">
                            @foreach ($teams as $team)
                                @php
                                    $entry = $sheetEntries->get($team->id);
                                    $sheetUrl = route('tasksheet.index', ['team' => $team->id]);
                                @endphp
                                <li class="flex items-center justify-between gap-3 text-sm">
                                    <span class="truncate text-slate-700">
                                        {{ $team->name }}
                                        @if ($entry?->isHalfDay())
                                            <span class="ml-1 rounded-full bg-amber-50 px-1.5 py-0.5 text-[11px] font-medium text-amber-700">Half day</span>
                                        @endif
                                    </span>
                                    @if ($entry?->isFullDayLeave())
                                        <a href="{{ $sheetUrl }}" class="flex-none rounded-full bg-sky-50 px-2.5 py-0.5 text-xs font-medium text-sky-700 hover:bg-sky-100">{{ $entry->leaveLabel() }}</a>
                                    @elseif ($entry?->isFullyFilled())
                                        <a href="{{ $sheetUrl }}" class="flex-none rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 hover:bg-emerald-100">Filled ✓</a>
                                    @elseif ($entry?->isPartiallyFilled())
                                        <a href="{{ $sheetUrl }}" title="Fill the remaining fields"
                                           class="flex-none rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100">Partially filled · {{ $entry->filledFieldCount() }}/{{ count(\App\Models\TasksheetEntry::TASK_FIELDS) }}</a>
                                    @else
                                        <a href="{{ $sheetUrl }}"
                                           class="flex-none rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700 hover:bg-amber-100">Not filled — fill now</a>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                {{-- Upcoming meetings --}}
                <div class="card card-pad">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-slate-700">Upcoming meetings</h3>
                        <a href="{{ route('calendar.index') }}" class="text-xs font-medium text-brand-600 hover:text-brand-700">Calendar →</a>
                    </div>
                    @if ($meetings->isEmpty())
                        <p class="mt-2 text-sm text-slate-400">No meetings in the next two weeks.</p>
                    @else
                        <ul class="mt-3 divide-y divide-slate-100">
                            @foreach ($meetings as $meeting)
                                <li class="py-2.5 first:pt-0 last:pb-0">
                                    <a href="{{ route('events.show', $meeting) }}" class="group block">
                                        <span class="block truncate text-sm font-medium text-slate-800 group-hover:text-brand-700">{{ $meeting->title }}</span>
                                        <span class="mt-0.5 block text-xs text-slate-400">
                                            {{ $meeting->starts_at->isToday() ? 'Today' : $meeting->starts_at->format('D, M j') }} · {{ $meeting->timeLabel() }}
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
