@php
    use App\Models\Release;
    use App\Models\Task;

    // Requires $task (release.project, assignee, subtasks loaded; comments_count) and $statuses.
    // The release is optional; an unlinked card gets no chip and a neutral stripe.
    // $canDelete (admins) adds the selection checkbox and the delete button.
    $canDelete ??= false;
    $subtaskCount = $task->subtasks->count();
    $progress = $task->subtaskProgress();
    $isDone = in_array($task->status, Task::DONE_STATUSES, true);
    $overdue = $task->due_date && ! $isDone && $task->due_date->lt(today());
    $dueToday = $task->due_date && ! $isDone && $task->due_date->isToday();
    $projectColor = $task->release?->project->color ?? '#94a3b8';
@endphp
<article class="board-card group" draggable="true" data-task-id="{{ $task->id }}">
    {{-- Project color stripe --}}
    <span class="absolute inset-y-0 left-0 w-1" style="background-color: {{ $projectColor }}" aria-hidden="true"></span>

    <div class="flex min-h-6 items-center gap-2">
        @if ($canDelete)
            <input type="checkbox" class="board-select" value="{{ $task->id }}" data-subtasks="{{ $subtaskCount }}"
                   aria-label="Select “{{ $task->title }}”">
        @endif
        {{-- Release chip only when the task is linked to one --}}
        @if ($task->release)
            <a href="{{ route('releases.show', $task->release) }}" draggable="false"
               class="flex min-w-0 items-center gap-1 text-[11px] font-medium text-slate-500 hover:text-brand-700">
                <x-icon name="rocket" variant="outline" class="h-3 w-3 flex-none" />
                <span class="truncate">{{ $task->release->name }}</span>
            </a>
        @endif
        {{-- Over the chip until hovered, so a narrow column still shows the release. --}}
        <div class="board-card-tools">
            <label for="move-{{ $task->id }}" class="sr-only">Move “{{ $task->title }}” to</label>
            <select id="move-{{ $task->id }}" class="board-move flex-none" draggable="false">
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected($task->status === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @if ($canDelete)
                <form method="POST" action="{{ route('board.tasks.destroy', $task) }}" class="board-delete-form flex-none"
                      data-confirm-title="Delete this task?" data-confirm-verb="Delete task"
                      data-confirm="“{{ Str::limit($task->title, 80) }}” goes to the trash, along with {{ $subtaskCount > 0 ? 'its '.$subtaskCount.' '.Str::plural('subtask', $subtaskCount).' and all comments' : 'its comments' }}. An admin can restore it for {{ Task::TRASH_DAYS }} days.">
                    @csrf @method('DELETE')
                    <button class="board-delete" aria-label="Delete “{{ $task->title }}”" title="Delete task">
                        <x-icon name="trash" variant="outline" class="h-3.5 w-3.5" />
                    </button>
                </form>
            @endif
        </div>
    </div>

    <a href="{{ route('tasks.show', $task) }}" draggable="false" data-task-panel="{{ route('tasks.panel', $task) }}" data-task-id="{{ $task->id }}"
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

    @php
        $blocked = $task->isBlocked();
        // Open work only: a card sitting still is worth noticing after a few days.
        $daysStill = in_array($task->status, Task::DONE_STATUSES, true) ? 0 : $task->daysInStatus();
        $showAge = $daysStill >= 3;
    @endphp
    @if ($showAge || $blocked || $task->priority || $task->labels->isNotEmpty() || $task->phase || $task->due_date)
        <div class="mt-2.5 flex flex-wrap items-center gap-1.5">
            @if ($showAge)
                <span @class(['badge', 'badge-danger' => $daysStill >= 14, 'badge-warning' => $daysStill >= 7 && $daysStill < 14, 'badge-slate' => $daysStill < 7]) title="{{ $daysStill }}d in this status">
                    <x-icon name="clock" variant="outline" class="h-3 w-3" />{{ $daysStill }}d
                </span>
            @endif
            @if ($blocked)
                <span class="badge badge-danger" title="{{ $task->blocked_reason ?: 'Waiting for another task' }}">
                    <x-icon name="exclamation-circle" variant="outline" class="h-3 w-3" />Blocked
                </span>
            @endif
            @if ($task->priority)
                <span @class([
                    'badge',
                    'badge-danger' => $task->priority === 'urgent',
                    'badge-warning' => $task->priority === 'high',
                    'badge-info' => $task->priority === 'medium',
                    'badge-slate' => $task->priority === 'low',
                ])>{{ $task->priorityLabel() }}</span>
            @endif
            @foreach ($task->labels as $label)
                <span class="badge badge-slate">
                    <span class="h-1.5 w-1.5 rounded-full" style="background-color: {{ $label->color }}" aria-hidden="true"></span>
                    {{ $label->name }}
                </span>
            @endforeach
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
        @include('partials.board-assignee', ['task' => $task])
        @include('ai.partials.board-suggest', ['task' => $task])
        <span class="tabular flex-none text-[11px] text-slate-400">{{ $task->reference() }}</span>
        @if ($task->comments_count > 0)
            <span class="flex flex-none items-center gap-1 text-xs text-slate-500" title="{{ $task->comments_count }} {{ Str::plural('comment', $task->comments_count) }}">
                <x-icon name="chat-bubbles" variant="outline" class="h-3.5 w-3.5" />{{ $task->comments_count }}
            </span>
        @endif
    </div>
</article>
