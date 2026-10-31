@php
    use App\Models\Release;
    use App\Models\Task;

    // Requires $task (release.project, assignee, subtasks loaded; comments_count) and $statuses.
    $progress = $task->subtaskProgress();
    $isDone = in_array($task->status, Task::DONE_STATUSES, true);
    $overdue = $task->due_date && ! $isDone && $task->due_date->lt(today());
    $dueToday = $task->due_date && ! $isDone && $task->due_date->isToday();
    $projectColor = $task->release->project->color ?? '#94a3b8';
@endphp
<article class="board-card group" draggable="true" data-task-id="{{ $task->id }}">
    {{-- Project color stripe --}}
    <span class="absolute inset-y-0 left-0 w-1" style="background-color: {{ $projectColor }}" aria-hidden="true"></span>

    <div class="flex items-center justify-between gap-2">
        <a href="{{ route('releases.show', $task->release) }}" draggable="false"
           class="flex min-w-0 items-center gap-1 text-[11px] font-medium text-slate-500 hover:text-brand-700">
            <x-icon name="rocket" variant="outline" class="h-3 w-3 flex-none" />
            <span class="truncate">{{ $task->release->name }}</span>
        </a>
        <label for="move-{{ $task->id }}" class="sr-only">Move “{{ $task->title }}” to</label>
        <select id="move-{{ $task->id }}" class="board-move flex-none" draggable="false">
            @foreach ($statuses as $value => $label)
                <option value="{{ $value }}" @selected($task->status === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <a href="{{ route('tasks.show', $task) }}" draggable="false"
       class="mt-1.5 block text-sm font-semibold leading-snug text-slate-900 hover:text-brand-700">{{ $task->title }}</a>

    @if ($progress['total'] > 0)
        @php $pct = (int) round($progress['done'] / $progress['total'] * 100); @endphp
        <div class="mt-2.5 flex items-center gap-2" title="{{ $progress['done'] }} of {{ $progress['total'] }} subtasks done">
            <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full {{ $pct === 100 ? 'bg-emerald-500' : 'bg-brand-500' }}" style="width: {{ $pct }}%"></div>
            </div>
            <span class="tabular text-[11px] font-medium text-slate-500">{{ $progress['done'] }}/{{ $progress['total'] }}</span>
        </div>
    @endif

    @if ($task->phase || $task->due_date)
        <div class="mt-2.5 flex flex-wrap items-center gap-1.5">
            @if ($task->phase)
                <span class="badge badge-slate">
                    <span class="h-1.5 w-1.5 rounded-full" style="background-color: {{ Release::PHASE_COLORS[$task->phase] ?? '#94a3b8' }}"></span>
                    {{ $task->phaseLabel() }}
                </span>
            @endif
            @if ($task->due_date)
                <span @class(['badge', 'badge-danger' => $overdue, 'badge-warning' => $dueToday, 'badge-slate' => ! $overdue && ! $dueToday])>
                    <x-icon name="calendar" variant="outline" class="h-3 w-3" />
                    {{ $overdue ? 'Overdue · ' : ($dueToday ? 'Today · ' : '') }}{{ $task->due_date->format('M j') }}
                </span>
            @endif
        </div>
    @endif

    <div class="mt-3 flex items-center justify-between gap-2 border-t border-slate-100 pt-2.5">
        @if ($task->assignee)
            <span class="flex min-w-0 items-center gap-1.5">
                <x-avatar :user="$task->assignee" size="sm" />
                <span class="truncate text-xs text-slate-600">{{ $task->assignee->name }}</span>
            </span>
        @else
            <span class="text-xs text-slate-400">Unassigned</span>
        @endif
        @if ($task->comments_count > 0)
            <span class="flex flex-none items-center gap-1 text-xs text-slate-500" title="{{ $task->comments_count }} {{ Str::plural('comment', $task->comments_count) }}">
                <x-icon name="chat-bubbles" variant="outline" class="h-3.5 w-3.5" />{{ $task->comments_count }}
            </span>
        @endif
    </div>
</article>
