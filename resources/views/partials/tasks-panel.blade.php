{{-- Expects: $release (with rootTasks.subtasks), $users --}}
@php use App\Models\Task; use App\Models\Release; @endphp

<div class="space-y-3">
    {{-- Add task --}}
    <form method="POST" action="{{ route('releases.tasks.store', $release) }}"
          class="rounded-lg border border-dashed border-slate-300 p-3">
        @csrf
        <div class="flex flex-wrap items-center gap-2">
            <input name="title" required placeholder="Add a task…"
                   class="min-w-[200px] flex-1 rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <select name="status" class="rounded-md border-slate-300 text-sm shadow-sm">
                @foreach (Task::STATUSES as $val => $label)
                    <option value="{{ $val }}">{{ $label }}</option>
                @endforeach
            </select>
            <select name="assignee_id" class="rounded-md border-slate-300 text-sm shadow-sm">
                <option value="">Unassigned</option>
                @foreach ($users as $u)
                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                @endforeach
            </select>
            <select name="phase" class="rounded-md border-slate-300 text-sm shadow-sm">
                <option value="">No phase</option>
                @foreach (Release::PHASES as $val => $label)
                    <option value="{{ $val }}">{{ $label }}</option>
                @endforeach
            </select>
            <input type="date" name="due_date" class="rounded-md border-slate-300 text-sm shadow-sm">
            <button class="btn-primary btn-sm">Add</button>
        </div>
    </form>

    {{-- Task list --}}
    @forelse ($release->rootTasks as $task)
        @php $progress = $task->subtaskProgress(); @endphp
        <div class="rounded-lg border border-slate-200 p-3" x-data="{ addSub: false }">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div class="min-w-0">
                    <a href="{{ route('tasks.show', $task) }}" class="text-sm font-medium text-slate-900 hover:text-indigo-600">{{ $task->title }}</a>
                    <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                        @if ($task->assignee) <span>👤 {{ $task->assignee->name }}</span> @endif
                        @if ($task->due_date) <span>📅 {{ $task->due_date->format('M j') }}</span> @endif
                        @if ($task->phase) <span class="rounded bg-slate-100 px-1.5 py-0.5">{{ $task->phaseLabel() }}</span> @endif
                        @if ($progress['total'] > 0) <span>☑ {{ $progress['done'] }}/{{ $progress['total'] }} subtasks</span> @endif
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <form method="POST" action="{{ route('tasks.status', $task) }}">
                        @csrf @method('PATCH')
                        <select name="status" onchange="this.form.submit()" class="rounded-md border-slate-300 py-1 text-xs shadow-sm">
                            @foreach (Task::STATUSES as $val => $label)
                                <option value="{{ $val }}" @selected($task->status === $val)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </form>
                    <button type="button" @click="addSub = !addSub" class="text-xs text-slate-400 hover:text-indigo-600" title="Add subtask">+ Sub</button>
                    <form method="POST" action="{{ route('tasks.destroy', $task) }}" data-confirm="Delete this task and its subtasks?" data-confirm-verb="Delete">
                        @csrf @method('DELETE')
                        <button class="text-xs text-slate-400 hover:text-rose-600">✕</button>
                    </form>
                </div>
            </div>

            {{-- Subtasks --}}
            @if ($task->subtasks->isNotEmpty())
                <ul class="mt-2 space-y-1 border-l-2 border-slate-100 pl-3">
                    @foreach ($task->subtasks as $sub)
                        <li class="flex items-center justify-between gap-2">
                            <a href="{{ route('tasks.show', $sub) }}" class="text-sm text-slate-700 hover:text-indigo-600 {{ $sub->isDone() ? 'line-through text-slate-400' : '' }}">{{ $sub->title }}</a>
                            <div class="flex items-center gap-2">
                                @include('partials.status-badge', ['status' => $sub->status])
                                <form method="POST" action="{{ route('tasks.destroy', $sub) }}" data-confirm="Delete this subtask?" data-confirm-verb="Delete">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-slate-300 hover:text-rose-600">✕</button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif

            {{-- Add subtask --}}
            <form x-show="addSub" x-cloak method="POST" action="{{ route('tasks.subtasks.store', $task) }}" class="mt-2 flex gap-2 pl-3">
                @csrf
                <input name="title" required placeholder="Subtask title…"
                       class="flex-1 rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <button class="rounded-md bg-slate-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-900">Add subtask</button>
            </form>
        </div>
    @empty
        <p class="py-4 text-center text-sm text-slate-400">No tasks yet. Add the first one above.</p>
    @endforelse
</div>
