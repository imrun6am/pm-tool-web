@php
    use App\Models\TaskTimeLog;
    use App\Support\Duration;

    // Requires $task, $timeSummary and $timeEntries.
    $canLog = auth()->user()->can('create', [TaskTimeLog::class, $task]);
    $canSeeEntries = auth()->user()->can('viewEntries', [TaskTimeLog::class, $task]);
@endphp
<x-live-region id="task-time">
    <section class="card" aria-labelledby="time-title">
        <div class="card-header">
            <div>
                <h2 id="time-title" class="card-title">Time logged</h2>
                @if ($timeSummary['subtask_minutes'] > 0)
                    <p class="card-subtitle">Includes {{ Duration::format($timeSummary['subtask_minutes']) }} on subtasks</p>
                @endif
                @if ($timeSummary['estimate_minutes'])
                    <p @class(['card-subtitle', '!text-danger-600' => $timeSummary['total_minutes'] > $timeSummary['estimate_minutes']])>
                        Estimated {{ Duration::format($timeSummary['estimate_minutes']) }}
                    </p>
                @endif
            </div>
            <span class="tabular text-lg font-semibold tracking-tight text-slate-900">{{ Duration::format($timeSummary['total_minutes']) }}</span>
        </div>

        @if ($errors->hasAny(['minutes', 'logged_on', 'note']))
            <div class="border-b border-slate-100 px-5 py-3">
                @foreach (['minutes', 'logged_on', 'note'] as $field)
                    @error($field)<p class="field-error" role="alert">{{ $message }}</p>@enderror
                @endforeach
            </div>
        @endif

        @if ($canLog)
            <form method="POST" action="{{ route('tasks.time-logs.store', $task) }}" class="space-y-2 border-b border-slate-100 px-5 py-4">
                @csrf
                <div class="flex gap-2">
                    <div class="w-24 flex-none">
                        <label for="time-duration" class="sr-only">Time spent</label>
                        <input id="time-duration" name="duration" required placeholder="1h 30m" value="{{ old('duration') }}"
                               class="field-control field-sm" autocomplete="off">
                    </div>
                    <div class="min-w-0 flex-1">
                        <label for="time-date" class="sr-only">Date</label>
                        <input id="time-date" type="date" name="logged_on" required max="{{ today()->toDateString() }}"
                               value="{{ old('logged_on', today()->toDateString()) }}" class="field-control field-sm">
                    </div>
                </div>
                <label for="time-note" class="sr-only">Note (optional)</label>
                <input id="time-note" name="note" maxlength="500" placeholder="What did you work on? (optional)" value="{{ old('note') }}"
                       class="field-control field-sm" autocomplete="off">
                <button class="btn-primary btn-sm">Log time</button>
            </form>
        @endif

        @if ($canSeeEntries)
            @forelse ($timeEntries as $entry)
                <div x-data="{ editing: false }" class="group border-b border-slate-100 px-5 py-3 last:border-b-0">
                    <div x-show="! editing" class="flex items-start gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-baseline gap-x-2 text-sm">
                                <span class="tabular font-semibold text-slate-900">{{ Duration::format($entry->minutes) }}</span>
                                <span class="text-slate-600">{{ $entry->user?->name ?? 'Former user' }}</span>
                                <span class="text-xs text-slate-500">{{ $entry->logged_on->format('M j, Y') }}</span>
                            </p>
                            @if ($entry->task_id !== $task->id)
                                <p class="mt-0.5 truncate text-xs text-slate-500">Subtask: {{ $entry->task->title }}</p>
                            @endif
                            @if ($entry->note)
                                <p class="mt-0.5 break-words text-xs text-slate-600">{{ $entry->note }}</p>
                            @endif
                        </div>
                        @can('update', $entry)
                            <div class="flex flex-none items-center gap-1">
                                <button type="button" @click="editing = true" class="btn-ghost btn-sm btn-icon h-7 w-7 text-slate-400 hover:text-brand-700" aria-label="Edit time entry">
                                    <x-icon name="pencil" class="h-4 w-4" />
                                </button>
                                <form method="POST" action="{{ route('time-logs.destroy', $entry) }}" data-confirm="Delete this time entry?" data-confirm-verb="Delete">
                                    @csrf @method('DELETE')
                                    <button class="btn-ghost btn-sm btn-icon h-7 w-7 text-slate-400 hover:text-danger-600" aria-label="Delete time entry">
                                        <x-icon name="x-mark" variant="outline" class="h-4 w-4" />
                                    </button>
                                </form>
                            </div>
                        @endcan
                    </div>
                    @can('update', $entry)
                        <form x-show="editing" x-cloak method="POST" action="{{ route('time-logs.update', $entry) }}" class="space-y-2">
                            @csrf @method('PUT')
                            <div class="flex gap-2">
                                <label for="time-duration-{{ $entry->id }}" class="sr-only">Time spent</label>
                                <input id="time-duration-{{ $entry->id }}" name="duration" required value="{{ Duration::format($entry->minutes) }}"
                                       class="field-control field-sm w-24 flex-none" autocomplete="off">
                                <label for="time-date-{{ $entry->id }}" class="sr-only">Date</label>
                                <input id="time-date-{{ $entry->id }}" type="date" name="logged_on" required max="{{ today()->toDateString() }}"
                                       value="{{ $entry->logged_on->toDateString() }}" class="field-control field-sm min-w-0 flex-1">
                            </div>
                            <label for="time-note-{{ $entry->id }}" class="sr-only">Note (optional)</label>
                            <input id="time-note-{{ $entry->id }}" name="note" maxlength="500" value="{{ $entry->note }}" class="field-control field-sm" autocomplete="off">
                            <div class="flex items-center gap-2">
                                <button class="btn-primary btn-sm">Save</button>
                                <button type="button" @click="editing = false" class="btn-ghost btn-sm">Cancel</button>
                            </div>
                        </form>
                    @endcan
                </div>
            @empty
                <p class="px-5 py-4 text-sm text-slate-500">No time logged yet.</p>
            @endforelse
        @endif
    </section>
</x-live-region>
