@php
    use App\Models\Task;
    use App\Models\Release;

    $crumbs = [$task->release->name => route('releases.show', $task->release)];
    if ($task->isSubtask() && $task->parent) {
        $crumbs[$task->parent->title] = route('tasks.show', $task->parent);
    }
    $crumbs[$task->title] = null;
    $progress = $task->subtaskProgress();
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$task->title" :breadcrumbs="$crumbs" />
        <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-slate-500">
            @include('partials.status-badge', ['status' => $task->status])
            @if ($task->isSubtask())
                <span class="badge badge-slate">Subtask</span>
            @endif
            @if ($task->phaseLabel())
                <span class="badge badge-violet">{{ $task->phaseLabel() }}</span>
            @endif
            <span class="flex items-center gap-1.5">
                @if ($task->assignee)
                    <x-avatar :user="$task->assignee" size="sm" />
                    {{ $task->assignee->name }}@include('partials.user-tag', ['tagUser' => $task->assignee])
                @else
                    <span class="text-slate-400">Unassigned</span>
                @endif
            </span>
            @if ($task->due_date)
                <span class="flex items-center gap-1.5">
                    <x-icon name="calendar" variant="outline" class="h-4 w-4 text-slate-400" />
                    Due {{ $task->due_date->format('M j, Y') }}
                </span>
            @endif
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container">
            {{-- Two rows on lg: the document sits in row 1, subtasks + comments in
                 row 2, and the properties panel spans both on the right. On small
                 screens the DOM order (document, properties, discussion) keeps the
                 Save button close to the fields it submits. --}}
            <div class="mx-auto grid max-w-6xl items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:grid-rows-[auto_1fr]">

                {{-- Document: title + description. The properties panel's controls
                     join this form through their form="task-form" attribute. --}}
                <form id="task-form" method="POST" action="{{ route('tasks.update', $task) }}" class="card lg:col-start-1 lg:row-start-1">
                    @csrf @method('PUT')
                    <div class="px-3 pt-4 sm:px-6 sm:pt-6">
                        <label for="task-title" class="sr-only">Title</label>
                        <input id="task-title" name="title" value="{{ old('title', $task->title) }}" required
                               placeholder="Task title" @invalid('title')
                               class="block w-full rounded-lg border border-transparent bg-transparent px-2 py-1.5 text-xl font-semibold tracking-tight text-slate-900 shadow-none transition-colors placeholder:text-slate-300 hover:border-slate-200 focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/15 aria-[invalid=true]:border-danger-500 sm:text-2xl">
                        @error('title')
                            <p id="title-error" class="field-error px-2">
                                <x-icon name="exclamation-circle" variant="outline" class="h-4 w-4 flex-none" />{{ $message }}
                            </p>
                        @enderror
                    </div>
                    <div class="px-3 pb-4 pt-1 sm:px-6 sm:pb-6">
                        <label for="task-description" class="sr-only">Description</label>
                        <textarea id="task-description" name="description" rows="6" placeholder="Add a description…" @invalid('description')
                                  class="block min-h-[9rem] w-full resize-y rounded-lg border border-transparent bg-transparent px-2 py-1.5 text-[15px] leading-7 text-slate-700 shadow-none transition-colors placeholder:text-slate-400 hover:border-slate-200 focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/15 aria-[invalid=true]:border-danger-500">{{ old('description', $task->description) }}</textarea>
                        @error('description')
                            <p id="description-error" class="field-error px-2">
                                <x-icon name="exclamation-circle" variant="outline" class="h-4 w-4 flex-none" />{{ $message }}
                            </p>
                        @enderror
                    </div>
                </form>

                {{-- Properties --}}
                <aside class="lg:sticky lg:top-20 lg:col-start-2 lg:row-span-2 lg:row-start-1">
                    <section class="card">
                        <h2 class="card-header card-title">Properties</h2>
                        <div class="divide-y divide-slate-100 text-sm">
                            <div class="grid grid-cols-[6rem_minmax(0,1fr)] items-center gap-3 px-5 py-2.5">
                                <label for="task-status" class="text-slate-500">Status</label>
                                <select id="task-status" name="status" form="task-form" @invalid('status') class="field-select field-sm !mt-0">
                                    @foreach (Task::STATUSES as $val => $label)
                                        <option value="{{ $val }}" @selected(old('status', $task->status) === $val)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="grid grid-cols-[6rem_minmax(0,1fr)] items-center gap-3 px-5 py-2.5">
                                <label for="task-assignee" class="text-slate-500">Assignee</label>
                                <select id="task-assignee" name="assignee_id" form="task-form" @invalid('assignee_id') class="field-select field-sm !mt-0">
                                    <option value="">Unassigned</option>
                                    @foreach ($users as $u)
                                        <option value="{{ $u->id }}" @selected((int) old('assignee_id', $task->assignee_id) === $u->id)>{{ $u->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="grid grid-cols-[6rem_minmax(0,1fr)] items-center gap-3 px-5 py-2.5">
                                <label for="task-due" class="text-slate-500">Due date</label>
                                <input id="task-due" type="date" name="due_date" form="task-form" value="{{ old('due_date', optional($task->due_date)->toDateString()) }}" @invalid('due_date')
                                       class="field-input field-sm !mt-0">
                            </div>
                            <div class="grid grid-cols-[6rem_minmax(0,1fr)] items-center gap-3 px-5 py-2.5">
                                <label for="task-phase" class="text-slate-500">Phase</label>
                                <select id="task-phase" name="phase" form="task-form" @invalid('phase') class="field-select field-sm !mt-0">
                                    <option value="">No phase</option>
                                    @foreach (Release::PHASES as $val => $label)
                                        <option value="{{ $val }}" @selected(old('phase', $task->phase) === $val)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @foreach (['status', 'assignee_id', 'due_date', 'phase'] as $field)
                                @error($field)
                                    <p id="{{ $field }}-error" class="field-error !mt-0 px-5 py-2.5">
                                        <x-icon name="exclamation-circle" variant="outline" class="h-4 w-4 flex-none" />{{ $message }}
                                    </p>
                                @enderror
                            @endforeach
                        </div>
                        <div class="card-footer flex-col items-stretch gap-3 sm:px-5">
                            <p class="flex min-w-0 items-center gap-1.5 text-xs text-slate-500">
                                @if ($task->creator)
                                    <x-avatar :user="$task->creator" size="xs" />
                                    <span class="truncate">Created by {{ $task->creator->name }} · {{ $task->created_at->diffForHumans() }}</span>
                                @else
                                    <span>Created {{ $task->created_at->diffForHumans() }}</span>
                                @endif
                            </p>
                            <button form="task-form" class="btn-primary btn-sm w-full">
                                <x-icon name="check" variant="outline" class="h-4 w-4" />Save task
                            </button>
                        </div>
                    </section>
                </aside>

                {{-- Discussion: subtasks + comments --}}
                <div class="min-w-0 space-y-6 lg:col-start-1 lg:row-start-2">
                    @unless ($task->isSubtask())
                        <section class="card">
                            <div class="card-header">
                                <div>
                                    <h2 class="card-title">Subtasks</h2>
                                    @if ($progress['total'] > 0)
                                        <p class="card-subtitle">{{ $progress['done'] }} of {{ $progress['total'] }} done</p>
                                    @endif
                                </div>
                                <span class="badge badge-slate">{{ $task->subtasks->count() }}</span>
                            </div>

                            @if ($task->subtasks->isNotEmpty())
                                <ul class="divide-y divide-slate-100">
                                    @foreach ($task->subtasks as $sub)
                                        <li class="flex items-center justify-between gap-3 px-5 py-2.5 sm:px-6">
                                            <a href="{{ route('tasks.show', $sub) }}" class="min-w-0 truncate text-sm font-medium hover:text-brand-700 {{ $sub->isDone() ? 'text-slate-400 line-through' : 'text-slate-800' }}">{{ $sub->title }}</a>
                                            <div class="flex flex-none items-center gap-2">
                                                @if ($sub->assignee)
                                                    <span class="hidden items-center gap-1.5 text-xs text-slate-500 sm:flex">
                                                        <x-avatar :user="$sub->assignee" size="xs" />{{ $sub->assignee->name }}
                                                    </span>
                                                @endif
                                                @include('partials.status-badge', ['status' => $sub->status])
                                                <form method="POST" action="{{ route('tasks.destroy', $sub) }}" data-confirm="Delete this subtask?" data-confirm-verb="Delete">
                                                    @csrf @method('DELETE')
                                                    <button class="btn-ghost btn-sm btn-icon h-7 w-7 text-slate-400 hover:text-danger-600" aria-label="Delete subtask {{ $sub->title }}">
                                                        <x-icon name="x-mark" variant="outline" class="h-4 w-4" />
                                                    </button>
                                                </form>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            <form method="POST" action="{{ route('tasks.subtasks.store', $task) }}" @class(['flex gap-2 px-5 py-4 sm:px-6', 'border-t border-slate-100' => $task->subtasks->isNotEmpty()])>
                                @csrf
                                <label for="new-subtask" class="sr-only">New subtask</label>
                                <input id="new-subtask" name="title" required placeholder="Add a subtask…"
                                       class="field-control field-sm min-w-0 flex-1">
                                <button class="btn-secondary btn-sm">
                                    <x-icon name="plus" variant="outline" class="h-4 w-4" />Add subtask
                                </button>
                            </form>
                        </section>
                    @endunless

                    <section class="card">
                        <div class="card-header">
                            <h2 class="card-title">Comments</h2>
                            <span class="badge badge-slate">{{ $task->comments->count() }}</span>
                        </div>
                        <div class="p-5 sm:p-6">
                            @include('partials.comments', ['comments' => $task->comments, 'storeUrl' => route('tasks.comments.store', $task)])
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
