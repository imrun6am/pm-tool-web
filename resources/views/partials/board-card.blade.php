@php $progress = $task->subtaskProgress(); @endphp
<div class="board-card group cursor-grab rounded-lg border border-slate-200 bg-white p-3 shadow-sm active:cursor-grabbing"
     draggable="true" data-task-id="{{ $task->id }}">
    <div class="flex items-start gap-2">
        <span class="mt-1 h-2.5 w-2.5 flex-none rounded-full" style="background-color: {{ $task->release->project->color ?? '#94a3b8' }}"></span>
        <div class="min-w-0 flex-1">
            <a href="{{ route('tasks.show', $task) }}" draggable="false" class="block text-sm font-medium text-slate-900 hover:text-indigo-600">{{ $task->title }}</a>
            <a href="{{ route('releases.show', $task->release) }}" draggable="false" class="mt-0.5 block truncate text-xs text-slate-400 hover:text-indigo-600">{{ $task->release->name }}</a>
        </div>
    </div>

    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
        @if ($task->phase)
            <span class="inline-flex items-center gap-1">
                <span class="h-2 w-2 rounded-sm" style="background-color: {{ \App\Models\Release::PHASE_COLORS[$task->phase] ?? '#94a3b8' }}"></span>
                {{ $task->phaseLabel() }}
            </span>
        @endif
        @if ($task->due_date)<span>📅 {{ $task->due_date->format('M j') }}</span>@endif
        @if ($progress['total'] > 0)<span>☑ {{ $progress['done'] }}/{{ $progress['total'] }}</span>@endif
        @if ($task->comments_count > 0)<span>💬 {{ $task->comments_count }}</span>@endif
    </div>

    @if ($task->assignee)
        <div class="mt-2 flex items-center gap-1.5">
            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-indigo-100 text-[10px] font-semibold text-indigo-700">
                {{ strtoupper(substr($task->assignee->name, 0, 1)) }}
            </span>
            <span class="text-xs text-slate-500">{{ $task->assignee->name }}</span>
        </div>
    @endif
</div>
