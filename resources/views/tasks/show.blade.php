@php
    use App\Models\Task;
    use App\Models\Release;

    // Breadcrumb: back to the board, through the release (if any) and the parent task.
    $boardUrl = route('board.index', $task->release ? ['release_id' => $task->release_id] : []);
    $crumbs = ['Board' => $boardUrl];
    if ($task->release) {
        $crumbs[$task->release->name] = route('releases.show', $task->release);
    }
    if ($task->isSubtask() && $task->parent) {
        $crumbs[$task->parent->title] = route('tasks.show', $task->parent);
    }
    $crumbs[$task->title] = null;

    $progress = $task->subtaskProgress();
    $pct = $progress['total'] > 0 ? (int) round($progress['done'] / $progress['total'] * 100) : 0;
    $isDone = in_array($task->status, Task::DONE_STATUSES, true);
    $overdue = $task->due_date && ! $isDone && $task->due_date->lt(today());
    $dueToday = $task->due_date && ! $isDone && $task->due_date->isToday();
    $me = auth()->user();

    // Status dots from the semantic palette (literal classes so Tailwind keeps them).
    $statusDots = [
        'todo' => 'bg-slate-400',
        'in_progress' => 'bg-info-500',
        'in_review' => 'bg-warning-500',
        'recheck' => 'bg-orange-500',
        'done' => 'bg-success-500',
        'archive' => 'bg-slate-300',
    ];
    $row = 'grid grid-cols-[6.5rem_minmax(0,1fr)] items-center gap-3 px-5 py-2.5';
    $rowLabel = 'flex items-center gap-2 text-slate-500';
    $rowIcon = 'h-4 w-4 flex-none text-slate-400';
    $sectionLabel = 'mb-1 flex items-center gap-1.5 px-2 text-xs font-semibold uppercase tracking-wide text-slate-400';
    $inline = 'block w-full rounded-lg border border-transparent bg-transparent px-2 py-1.5 shadow-none transition-colors hover:border-slate-200 hover:bg-slate-50/60 focus:border-brand-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-brand-500/15 aria-[invalid=true]:border-danger-500';
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$task->title" :breadcrumbs="$crumbs">
            <x-slot name="actions">
                <a href="{{ $boardUrl }}" class="btn-secondary btn-sm">
                    <x-icon name="view-columns" variant="outline" class="h-4 w-4" />Open board
                </a>
            </x-slot>
        </x-page-header>

        {{-- At a glance: the properties panel, summarised --}}
        <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
            @include('partials.status-badge', ['status' => $task->status])
            @if ($task->isSubtask())
                <span class="badge badge-slate">Subtask</span>
            @endif
            @if ($task->phaseLabel())
                <span class="badge badge-slate">
                    <span class="h-1.5 w-1.5 rounded-full" style="background-color: {{ Release::PHASE_COLORS[$task->phase] ?? '#94a3b8' }}"></span>
                    {{ $task->phaseLabel() }}
                </span>
            @endif
            @if ($task->due_date)
                <span @class(['badge', 'badge-danger' => $overdue, 'badge-warning' => $dueToday, 'badge-slate' => ! $overdue && ! $dueToday])>
                    <x-icon name="calendar" variant="outline" class="h-3.5 w-3.5" />
                    {{ $overdue ? 'Overdue · ' : ($dueToday ? 'Due today · ' : 'Due ') }}{{ $task->due_date->format('M j, Y') }}
                </span>
            @endif
            @if ($task->release)
                <a href="{{ route('releases.show', $task->release) }}" class="badge badge-brand hover:bg-brand-100">
                    <x-icon name="rocket" variant="outline" class="h-3.5 w-3.5" />{{ $task->release->name }}
                </a>
            @endif
            @if ($task->team)
                <span class="badge badge-slate">
                    <span class="h-1.5 w-1.5 rounded-full" style="background-color: {{ $task->team->color }}"></span>{{ $task->team->name }}
                </span>
            @endif
            <span class="ml-1 flex items-center gap-1.5 text-slate-500">
                @if ($task->assignee)
                    <x-avatar :user="$task->assignee" size="sm" />
                    <span class="text-slate-700">{{ $task->assignee->name }}</span>@include('partials.user-tag', ['tagUser' => $task->assignee])
                @else
                    <span class="text-slate-400">Unassigned</span>
                @endif
            </span>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        {{-- `dirty` lights the save bar once any task-form control changes —
             including the properties panel's, which join via form="task-form". --}}
        <div class="app-container"
             x-data="{ dirty: false }"
             @input="if ($event.target.form?.id === 'task-form') dirty = true"
             @change="if ($event.target.form?.id === 'task-form') dirty = true">

            {{-- Full width. On lg the document column sits left (row 1: the task,
                 row 2: subtasks + comments) and the properties column spans both
                 rows on the right. On small screens the DOM order (task,
                 properties, discussion) keeps Save next to the fields it submits. --}}
            <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem] lg:grid-rows-[auto_1fr] xl:grid-cols-[minmax(0,1fr)_24rem]">

                {{-- Document: title + description --}}
                <form id="task-form" method="POST" action="{{ route('tasks.update', $task) }}"
                      @keydown.meta.enter.prevent="$el.requestSubmit()" @keydown.ctrl.enter.prevent="$el.requestSubmit()"
                      @reset="dirty = false"
                      class="card overflow-hidden lg:col-start-1 lg:row-start-1">
                    @csrf @method('PUT')
                    <div class="px-3 pt-4 sm:px-6 sm:pt-6">
                        <label for="task-title" class="{{ $sectionLabel }}">Title</label>
                        <input id="task-title" name="title" value="{{ old('title', $task->title) }}" required
                               placeholder="Task title" @invalid('title')
                               class="{{ $inline }} text-xl font-semibold tracking-tight text-slate-900 placeholder:text-slate-300 sm:text-2xl">
                        @error('title')
                            <p id="title-error" class="field-error px-2">
                                <x-icon name="exclamation-circle" variant="outline" class="h-4 w-4 flex-none" />{{ $message }}
                            </p>
                        @enderror
                    </div>
                    <div class="px-3 pb-4 pt-3 sm:px-6 sm:pb-6">
                        <label for="task-description" class="{{ $sectionLabel }}">
                            <x-icon name="document-text" variant="outline" class="h-3.5 w-3.5" />Description
                        </label>
                        <textarea id="task-description" name="description" rows="8" placeholder="Add context, acceptance criteria, links…" @invalid('description')
                                  class="{{ $inline }} min-h-[11rem] resize-y text-[15px] leading-7 text-slate-700 placeholder:text-slate-400">{{ old('description', $task->description) }}</textarea>
                        @error('description')
                            <p id="description-error" class="field-error px-2">
                                <x-icon name="exclamation-circle" variant="outline" class="h-4 w-4 flex-none" />{{ $message }}
                            </p>
                        @enderror
                    </div>

                    {{-- Save bar: quiet until something changes --}}
                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 bg-slate-50/70 px-5 py-3 transition-colors sm:px-6"
                         :class="dirty && '!bg-brand-50/70'">
                        <p class="flex items-center gap-1.5 text-xs text-slate-500" aria-live="polite">
                            <span x-show="!dirty" class="flex items-center gap-1.5">
                                <x-icon name="pencil-square" variant="outline" class="h-3.5 w-3.5 text-slate-400" />
                                Click any field to edit · <kbd class="rounded border border-slate-200 bg-white px-1 font-sans text-[10px] text-slate-500">⌘/Ctrl ↵</kbd> saves
                            </span>
                            <span x-show="dirty" x-cloak class="flex items-center gap-1.5 font-medium text-brand-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-brand-500"></span>Unsaved changes
                            </span>
                        </p>
                        <div class="flex items-center gap-2">
                            <button type="reset" x-show="dirty" x-cloak class="btn-ghost btn-sm">Discard</button>
                            <button class="btn-primary btn-sm">
                                <x-icon name="check" variant="outline" class="h-4 w-4" />Save changes
                            </button>
                        </div>
                    </div>
                </form>

                {{-- Properties column --}}
                <aside class="space-y-6 lg:sticky lg:top-20 lg:col-start-2 lg:row-span-2 lg:row-start-1">
                    <section class="card" aria-labelledby="properties-title">
                        <div class="card-header">
                            <h2 id="properties-title" class="card-title">Properties</h2>
                        </div>
                        <div class="divide-y divide-slate-100 text-sm">
                            <div class="{{ $row }}" x-data="{ s: @js(old('status', $task->status)), dots: @js($statusDots) }">
                                <label for="task-status" class="{{ $rowLabel }}">
                                    <span class="h-2.5 w-2.5 flex-none rounded-full {{ $statusDots[$task->status] ?? 'bg-slate-400' }}" :class="dots[s] ?? 'bg-slate-400'"></span>Status
                                </label>
                                <select id="task-status" name="status" form="task-form" x-model="s" @invalid('status') class="field-select field-sm !mt-0">
                                    @foreach (Task::STATUSES as $val => $label)
                                        <option value="{{ $val }}" @selected(old('status', $task->status) === $val)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="{{ $row }}">
                                <label for="task-assignee" class="{{ $rowLabel }}"><x-icon name="user" class="{{ $rowIcon }}" />Assignee</label>
                                <select id="task-assignee" name="assignee_id" form="task-form" @invalid('assignee_id') class="field-select field-sm !mt-0">
                                    <option value="">Unassigned</option>
                                    @foreach ($users as $u)
                                        <option value="{{ $u->id }}" @selected((int) old('assignee_id', $task->assignee_id) === $u->id)>{{ $u->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            {{-- Team: a linked task takes its release's team; a subtask its parent's --}}
                            <div class="{{ $row }}">
                                @if ($task->isSubtask() || $task->release || ! $teamPicker)
                                    <span class="{{ $rowLabel }}"><x-icon name="user-group" variant="outline" class="{{ $rowIcon }}" />Team</span>
                                    <span class="flex min-w-0 items-center gap-1.5 px-1 text-slate-700"
                                          title="{{ $task->isSubtask() ? 'A subtask follows its parent task’s team' : ($task->release ? 'Set by the linked release' : 'Your team') }}">
                                        @if ($task->team)
                                            <span class="h-2 w-2 flex-none rounded-full" style="background-color: {{ $task->team->color }}"></span>
                                        @endif
                                        <span class="truncate">{{ $task->team?->name ?? 'No team' }}</span>
                                        <x-icon name="lock-closed" variant="outline" class="ml-auto h-3.5 w-3.5 flex-none text-slate-300" />
                                    </span>
                                @else
                                    <label for="task-team" class="{{ $rowLabel }}"><x-icon name="user-group" variant="outline" class="{{ $rowIcon }}" />Team</label>
                                    <select id="task-team" name="team_id" form="task-form" @invalid('team_id') class="field-select field-sm !mt-0">
                                        @unless ($me->hasLimitedAccess())
                                            <option value="">No team</option>
                                        @endunless
                                        @foreach ($teams as $tm)
                                            <option value="{{ $tm->id }}" @selected((int) old('team_id', $task->team_id) === $tm->id)>{{ $tm->name }}</option>
                                        @endforeach
                                    </select>
                                @endif
                            </div>
                            {{-- Release: optional on a task; a subtask follows its parent --}}
                            <div class="{{ $row }}">
                                @if ($task->isSubtask())
                                    <span class="{{ $rowLabel }}"><x-icon name="rocket" variant="outline" class="{{ $rowIcon }}" />Release</span>
                                    <span class="flex min-w-0 items-center gap-1.5 px-1 text-slate-700" title="A subtask follows its parent task’s release">
                                        <span class="truncate">{{ $task->release?->name ?? 'No release' }}</span>
                                        <x-icon name="lock-closed" variant="outline" class="ml-auto h-3.5 w-3.5 flex-none text-slate-300" />
                                    </span>
                                @else
                                    <label for="task-release" class="{{ $rowLabel }}"><x-icon name="rocket" variant="outline" class="{{ $rowIcon }}" />Release</label>
                                    <select id="task-release" name="release_id" form="task-form" @invalid('release_id') class="field-select field-sm !mt-0">
                                        <option value="">No release</option>
                                        @foreach ($releases as $r)
                                            <option value="{{ $r->id }}" @selected((int) old('release_id', $task->release_id) === $r->id)>{{ $r->name }}</option>
                                        @endforeach
                                    </select>
                                @endif
                            </div>
                            <div class="{{ $row }}">
                                <label for="task-phase" class="{{ $rowLabel }}"><x-icon name="chart-bar" variant="outline" class="{{ $rowIcon }}" />Phase</label>
                                <select id="task-phase" name="phase" form="task-form" @invalid('phase') class="field-select field-sm !mt-0">
                                    <option value="">No phase</option>
                                    @foreach (Release::PHASES as $val => $label)
                                        <option value="{{ $val }}" @selected(old('phase', $task->phase) === $val)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="{{ $row }}">
                                <label for="task-due" @class([$rowLabel, '!text-danger-600' => $overdue])>
                                    <x-icon name="calendar" variant="outline" @class([$rowIcon, '!text-danger-500' => $overdue]) />Due date
                                </label>
                                <input id="task-due" type="date" name="due_date" form="task-form" value="{{ old('due_date', optional($task->due_date)->toDateString()) }}" @invalid('due_date')
                                       @class(['field-input field-sm !mt-0', 'border-danger-300' => $overdue])>
                            </div>
                            @foreach (['release_id', 'team_id', 'status', 'assignee_id', 'due_date', 'phase'] as $f)
                                @error($f)
                                    <p id="{{ $f }}-error" class="field-error !mt-0 px-5 py-2.5">
                                        <x-icon name="exclamation-circle" variant="outline" class="h-4 w-4 flex-none" />{{ $message }}
                                    </p>
                                @enderror
                            @endforeach
                        </div>
                        <div class="card-footer sm:px-5">
                            <button form="task-form" class="btn-primary btn-sm w-full">
                                <x-icon name="check" variant="outline" class="h-4 w-4" />Save task
                            </button>
                        </div>
                    </section>

                    {{-- Details --}}
                    <section class="card" aria-labelledby="details-title">
                        <div class="card-header">
                            <h2 id="details-title" class="card-title">Details</h2>
                            <span class="tabular text-xs font-medium text-slate-400">#{{ $task->id }}</span>
                        </div>
                        <dl class="divide-y divide-slate-100 text-sm">
                            <div class="{{ $row }}">
                                <dt class="text-slate-500">Created by</dt>
                                <dd class="flex min-w-0 items-center gap-1.5 text-slate-700">
                                    @if ($task->creator)
                                        <x-avatar :user="$task->creator" size="xs" /><span class="truncate">{{ $task->creator->name }}</span>
                                    @else
                                        <span class="text-slate-400">Unknown</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="{{ $row }}">
                                <dt class="text-slate-500">Created</dt>
                                <dd class="text-slate-700" title="{{ $task->created_at->format('M j, Y g:i a') }}">{{ $task->created_at->format('M j, Y') }} <span class="text-slate-400">· {{ $task->created_at->diffForHumans() }}</span></dd>
                            </div>
                            <div class="{{ $row }}">
                                <dt class="text-slate-500">Updated</dt>
                                <dd class="text-slate-700" title="{{ $task->updated_at->format('M j, Y g:i a') }}">{{ $task->updated_at->diffForHumans() }}</dd>
                            </div>
                            @if ($task->isSubtask() && $task->parent)
                                <div class="{{ $row }}">
                                    <dt class="text-slate-500">Parent</dt>
                                    <dd class="min-w-0"><a href="{{ route('tasks.show', $task->parent) }}" class="block truncate font-medium text-brand-700 hover:text-brand-800">{{ $task->parent->title }}</a></dd>
                                </div>
                            @endif
                        </dl>
                    </section>

                    {{-- Claude: task writer and comment catch-up --}}
                    @include('ai.panels.task', ['task' => $task])

                    {{-- Activity (a planning surface — hidden from developers/QA, as on release pages) --}}
                    @unless ($me->hasLimitedAccess())
                        <section class="card" x-data="{ open: false }">
                            <button type="button" @click="open = !open" :aria-expanded="open.toString()" aria-controls="task-activity"
                                    class="card-header w-full text-left">
                                <h2 class="card-title flex items-center gap-2">
                                    <x-icon name="activity" class="h-4 w-4 text-slate-400" />Activity
                                </h2>
                                <span class="flex items-center gap-2 text-xs text-slate-400">
                                    <span class="tabular">{{ $activities->count() }}</span>
                                    <x-icon name="chevron-down" class="h-4 w-4 transition-transform" ::class="open && 'rotate-180'" />
                                </span>
                            </button>
                            <div id="task-activity" x-show="open" x-cloak class="max-h-96 overflow-y-auto border-t border-slate-100 px-4">
                                @include('partials.activity-list', ['activities' => $activities])
                            </div>
                        </section>
                    @endunless
                </aside>

                {{-- Discussion: subtasks + comments --}}
                <div class="min-w-0 space-y-6 lg:col-start-1 lg:row-start-2">
                    @unless ($task->isSubtask())
                        <section id="task-subtasks" class="card" aria-labelledby="subtasks-title">
                            <div class="card-header">
                                <div class="min-w-0">
                                    <h2 id="subtasks-title" class="card-title">Subtasks</h2>
                                    <p class="card-subtitle">
                                        @if ($progress['total'] > 0)
                                            {{ $progress['done'] }} of {{ $progress['total'] }} done
                                        @else
                                            Break the work into smaller steps.
                                        @endif
                                    </p>
                                </div>
                                @if ($progress['total'] > 0)
                                    <div class="flex w-32 flex-none items-center gap-2 sm:w-44" role="img" aria-label="{{ $pct }}% of subtasks done">
                                        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100">
                                            <div class="h-full rounded-full transition-[width] duration-300 {{ $pct === 100 ? 'bg-success-500' : 'bg-brand-500' }}" style="width: {{ $pct }}%"></div>
                                        </div>
                                        <span class="tabular text-xs font-semibold {{ $pct === 100 ? 'text-success-700' : 'text-slate-600' }}">{{ $pct }}%</span>
                                    </div>
                                @endif
                            </div>

                            @if ($task->subtasks->isNotEmpty())
                                <ul class="divide-y divide-slate-100">
                                    @foreach ($task->subtasks as $sub)
                                        <li class="group flex items-center gap-3 px-5 py-2.5 transition-colors hover:bg-slate-50/70 sm:px-6">
                                            <span @class([
                                                'flex h-5 w-5 flex-none items-center justify-center rounded-full ring-1 ring-inset',
                                                'bg-success-500 text-white ring-success-500' => $sub->isDone(),
                                                'bg-white text-transparent ring-slate-300' => ! $sub->isDone(),
                                            ]) aria-hidden="true">
                                                <x-icon name="check" variant="outline" class="h-3 w-3" />
                                            </span>
                                            <a href="{{ route('tasks.show', $sub) }}" class="min-w-0 flex-1 truncate text-sm font-medium hover:text-brand-700 {{ $sub->isDone() ? 'text-slate-400 line-through' : 'text-slate-800' }}">{{ $sub->title }}</a>
                                            <div class="flex flex-none items-center gap-2">
                                                @if ($sub->assignee)
                                                    <span class="hidden items-center gap-1.5 text-xs text-slate-500 sm:flex">
                                                        <x-avatar :user="$sub->assignee" size="xs" />{{ $sub->assignee->name }}
                                                    </span>
                                                @endif
                                                @include('partials.status-badge', ['status' => $sub->status])
                                                <form method="POST" action="{{ route('tasks.destroy', $sub) }}" data-confirm="Delete this subtask?" data-confirm-verb="Delete">
                                                    @csrf @method('DELETE')
                                                    <button class="btn-ghost btn-sm btn-icon h-7 w-7 text-slate-400 hover:text-danger-600 sm:opacity-0 sm:focus:opacity-100 sm:group-hover:opacity-100" aria-label="Delete subtask {{ $sub->title }}">
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
                                <div class="relative min-w-0 flex-1">
                                    <x-icon name="plus" variant="outline" class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                    <input id="new-subtask" name="title" required placeholder="Add a subtask and press Enter…"
                                           class="field-control field-sm w-full pl-8">
                                </div>
                                <button class="btn-secondary btn-sm">Add subtask</button>
                            </form>
                        </section>
                    @endunless

                    <section id="task-comments" class="card" aria-labelledby="comments-title">
                        <div class="card-header">
                            <h2 id="comments-title" class="card-title flex items-center gap-2">
                                <x-icon name="chat-bubbles" variant="outline" class="h-4 w-4 text-slate-400" />Comments
                            </h2>
                            <span class="badge badge-slate tabular">{{ $task->comments->count() }}</span>
                        </div>
                        <div class="p-5 sm:p-6">
                            @include('partials.comments', ['comments' => $task->comments, 'storeUrl' => route('tasks.comments.store', $task), 'composer' => false])

                            {{-- Composer --}}
                            <form method="POST" action="{{ route('tasks.comments.store', $task) }}"
                                  @class(['flex gap-3', 'mt-5 border-t border-slate-100 pt-5' => $task->comments->isNotEmpty(), 'mt-4' => $task->comments->isEmpty()])>
                                @csrf
                                <x-avatar :user="$me" class="mt-0.5" />
                                <div class="min-w-0 flex-1 rounded-xl border border-slate-200 bg-white shadow-sm transition focus-within:border-brand-500 focus-within:ring-4 focus-within:ring-brand-500/15">
                                    <label for="new-comment" class="sr-only">Write a comment</label>
                                    <textarea id="new-comment" name="body" rows="3" required placeholder="Write a comment…"
                                              @keydown.meta.enter.prevent="$el.form.requestSubmit()" @keydown.ctrl.enter.prevent="$el.form.requestSubmit()"
                                              class="block w-full resize-y rounded-t-xl border-0 bg-transparent px-3.5 py-3 text-sm text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-0">{{ old('body') }}</textarea>
                                    <div class="flex items-center justify-between gap-3 border-t border-slate-100 px-3 py-2">
                                        <span class="hidden text-xs text-slate-400 sm:inline">
                                            <kbd class="rounded border border-slate-200 bg-slate-50 px-1 font-sans text-[10px]">⌘/Ctrl ↵</kbd> to post
                                        </span>
                                        <button class="btn-primary btn-sm ml-auto">
                                            <x-icon name="chat-bubbles" variant="outline" class="h-4 w-4" />Comment
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
