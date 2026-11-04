@php
    use App\Models\Task;
    use App\Services\TaskService;

    $me = auth()->user();
    $teamChoice = ! $me->hasLimitedAccess() || $teams->count() > 1;
    $hasFilters = collect($filters)->except(['include_subtasks', 'sort', 'direction'])->filter()->isNotEmpty();

    // A column header: click to sort by it, click again to reverse.
    $sortUrl = fn (string $column) => route('tasks.index', array_filter([
        ...request()->except('page'),
        'sort' => $column,
        'direction' => $filters['sort'] === $column && $filters['direction'] === 'asc' ? 'desc' : 'asc',
    ]));
    $ariaSort = fn (string $column) => $filters['sort'] === $column ? ($filters['direction'] === 'asc' ? 'ascending' : 'descending') : 'none';
    $priorityBadge = ['urgent' => 'badge-danger', 'high' => 'badge-warning', 'medium' => 'badge-info', 'low' => 'badge-slate'];
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header title="All tasks" description="Find, sort and change tasks across every release and team.">
            <x-slot name="actions">
                @can('manageTrash', Task::class)
                    <a href="{{ route('tasks.trash') }}" class="btn-ghost btn-sm">
                        <x-icon name="trash" variant="outline" class="h-4 w-4" />Trash
                    </a>
                @endcan
                @include('partials.saved-views', ['savedViewPage' => 'tasks'])
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container space-y-4">
            <x-filter-bar :action="route('tasks.index')" :active="$hasFilters" :count="$tasks->total()" noun="task">
                <x-slot name="search">
                    <label for="task-search" class="sr-only">Search tasks</label>
                    <input id="task-search" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search by title…" class="filter-search">
                </x-slot>

                <label for="release_id" class="filter-label">Release</label>
                <select id="release_id" name="release_id" class="filter-select max-w-[12rem]">
                    <option value="">All releases</option>
                    <option value="none" @selected($filters['release_id'] === 'none')>No release</option>
                    @foreach ($releases as $r)
                        <option value="{{ $r->id }}" @selected($filters['release_id'] === $r->id)>{{ $r->name }}</option>
                    @endforeach
                </select>

                @if ($teamChoice)
                    <label for="team_id" class="filter-label">Team</label>
                    <select id="team_id" name="team_id" class="filter-select max-w-[10rem]">
                        <option value="">All teams</option>
                        <option value="none" @selected($filters['team_id'] === 'none')>No team</option>
                        @foreach ($teams as $tm)
                            <option value="{{ $tm->id }}" @selected($filters['team_id'] === $tm->id)>{{ $tm->name }}</option>
                        @endforeach
                    </select>
                @endif

                <label for="assignee_id" class="filter-label">Assignee</label>
                <select id="assignee_id" name="assignee_id" class="filter-select max-w-[11rem]">
                    <option value="">Anyone</option>
                    <option value="none" @selected($filters['assignee_id'] === 'none')>Unassigned</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}" @selected($filters['assignee_id'] === $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>

                <label for="status" class="filter-label">Status</label>
                <select id="status" name="status" class="filter-select max-w-[9rem]">
                    <option value="">Any status</option>
                    @foreach (Task::STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>

                <label for="priority" class="filter-label">Priority</label>
                <select id="priority" name="priority" class="filter-select max-w-[9rem]">
                    <option value="">Any priority</option>
                    <option value="none" @selected($filters['priority'] === 'none')>No priority</option>
                    @foreach (Task::PRIORITIES as $value => $label)
                        <option value="{{ $value }}" @selected($filters['priority'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>

                <label for="label_id" class="filter-label">Label</label>
                <select id="label_id" name="label_id" class="filter-select max-w-[9rem]">
                    <option value="">Any label</option>
                    @foreach ($labels as $l)
                        <option value="{{ $l->id }}" @selected($filters['label_id'] === $l->id)>{{ $l->name }}</option>
                    @endforeach
                </select>

                <label class="flex h-9 cursor-pointer items-center gap-2 rounded-lg bg-white px-3 text-sm font-medium text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50">
                    <input type="checkbox" name="blocked" value="1" @checked($filters['blocked']) class="checkbox-sm">
                    Blocked
                </label>
            </x-filter-bar>

            <x-live-region id="task-list">
                @if ($tasks->isEmpty())
                    <x-empty-state icon="check" title="{{ $hasFilters ? 'No tasks match these filters.' : 'No tasks yet.' }}" />
                @else
                    <div x-data="{
                            count: 0,
                            allOnPage: false,
                            boxes() { return [...$root.querySelectorAll('input[name=\'ids[]\']')]; },
                            sync() { const b = this.boxes(); this.count = b.filter((x) => x.checked).length; this.allOnPage = b.length > 0 && this.count === b.length; },
                            toggleAll(on) { this.boxes().forEach((x) => { x.checked = on; }); this.sync(); },
                         }" class="space-y-3">

                        {{-- Change the selected tasks --}}
                        <form id="bulk-form" method="POST" action="{{ route('tasks.bulk') }}" x-show="count > 0" x-cloak
                              class="card sticky top-[4.5rem] z-10 flex flex-wrap items-center gap-2 px-3.5 py-2.5">
                            @csrf
                            <span class="tabular text-sm font-semibold text-slate-800" aria-live="polite" x-text="count + ' selected'"></span>

                            <label for="bulk-status" class="sr-only">Status</label>
                            <select id="bulk-status" name="status" class="filter-select" data-native>
                                <option value="">Status: no change</option>
                                @foreach (Task::STATUSES as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>

                            <label for="bulk-assignee" class="sr-only">Assignee</label>
                            <select id="bulk-assignee" name="assignee_id" class="filter-select max-w-[12rem]" data-native>
                                <option value="">Assignee: no change</option>
                                <option value="{{ TaskService::NONE }}">Unassign</option>
                                @foreach ($users as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                @endforeach
                            </select>

                            <label for="bulk-priority" class="sr-only">Priority</label>
                            <select id="bulk-priority" name="priority" class="filter-select" data-native>
                                <option value="">Priority: no change</option>
                                <option value="{{ TaskService::NONE }}">No priority</option>
                                @foreach (Task::PRIORITIES as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>

                            <label for="bulk-label" class="sr-only">Add a label</label>
                            <select id="bulk-label" name="add_label_id" class="filter-select" data-native>
                                <option value="">Add label: none</option>
                                @foreach ($labels as $l)
                                    <option value="{{ $l->id }}">{{ $l->name }}</option>
                                @endforeach
                            </select>

                            <button class="btn-primary btn-sm" x-text="'Apply to ' + count"></button>
                            <button type="button" class="btn-ghost btn-sm" @click="toggleAll(false)">Clear</button>
                        </form>

                        @if ($errors->hasAny(['ids', 'ids.*', 'status', 'assignee_id', 'priority', 'add_label_id']))
                            <div class="card space-y-1 px-4 py-3">
                                @foreach ($errors->all() as $message)
                                    <p class="field-error" role="alert">{{ $message }}</p>
                                @endforeach
                            </div>
                        @endif

                        <div class="table-wrap">
                            <table class="table-base">
                                <thead>
                                    <tr>
                                        <th scope="col" class="w-8">
                                            <input type="checkbox" class="checkbox-sm" aria-label="Select all {{ $tasks->count() }} tasks on this page"
                                                   :checked="allOnPage" :indeterminate="count > 0 && ! allOnPage" @change="toggleAll($event.target.checked)">
                                        </th>
                                        <th scope="col" class="w-20">Key</th>
                                        <th scope="col" aria-sort="{{ $ariaSort('title') }}"><a href="{{ $sortUrl('title') }}" class="hover:text-brand-700">Title</a></th>
                                        <th scope="col" aria-sort="{{ $ariaSort('status') }}"><a href="{{ $sortUrl('status') }}" class="hover:text-brand-700">Status</a></th>
                                        <th scope="col" aria-sort="{{ $ariaSort('priority') }}" class="hidden sm:table-cell"><a href="{{ $sortUrl('priority') }}" class="hover:text-brand-700">Priority</a></th>
                                        <th scope="col" aria-sort="{{ $ariaSort('assignee') }}" class="hidden md:table-cell"><a href="{{ $sortUrl('assignee') }}" class="hover:text-brand-700">Assignee</a></th>
                                        <th scope="col" aria-sort="{{ $ariaSort('due_date') }}" class="hidden sm:table-cell"><a href="{{ $sortUrl('due_date') }}" class="hover:text-brand-700">Due</a></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($tasks as $task)
                                        @php
                                            $overdue = $task->due_date && ! in_array($task->status, Task::DONE_STATUSES, true) && $task->due_date->lt(today());
                                        @endphp
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="ids[]" value="{{ $task->id }}" form="bulk-form" class="checkbox-sm"
                                                       aria-label="Select {{ $task->reference() }}" @change="sync()">
                                            </td>
                                            <td class="tabular whitespace-nowrap text-xs text-slate-500">{{ $task->reference() }}</td>
                                            <td class="max-w-[28rem]">
                                                <a href="{{ route('tasks.show', $task) }}" data-task-panel="{{ route('tasks.panel', $task) }}" data-task-id="{{ $task->id }}" class="block truncate font-medium text-slate-900 hover:text-brand-700" title="{{ $task->title }}">{{ $task->title }}</a>
                                                @if ($task->isBlocked() || $task->labels->isNotEmpty() || $task->release)
                                                    <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                                        @if ($task->isBlocked())
                                                            <span class="badge badge-danger" title="{{ $task->blocked_reason ?: 'Waiting for another task' }}">Blocked</span>
                                                        @endif
                                                        @foreach ($task->labels as $l)
                                                            <span class="badge badge-slate"><span class="h-1.5 w-1.5 rounded-full" style="background-color: {{ $l->color }}" aria-hidden="true"></span>{{ $l->name }}</span>
                                                        @endforeach
                                                        @if ($task->release)
                                                            <span class="truncate text-xs text-slate-500">{{ $task->release->name }}</span>
                                                        @endif
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="whitespace-nowrap">@include('partials.status-badge', ['status' => $task->status])</td>
                                            <td class="hidden sm:table-cell">
                                                @if ($task->priority)
                                                    <span class="badge {{ $priorityBadge[$task->priority] ?? 'badge-slate' }}">{{ $task->priorityLabel() }}</span>
                                                @endif
                                            </td>
                                            <td class="hidden whitespace-nowrap md:table-cell">
                                                @if ($task->assignee)
                                                    <span class="flex items-center gap-1.5 text-sm text-slate-700"><x-avatar :user="$task->assignee" size="xs" />{{ $task->assignee->name }}</span>
                                                @else
                                                    <span class="text-sm text-slate-400">Unassigned</span>
                                                @endif
                                            </td>
                                            <td @class(['hidden whitespace-nowrap text-sm sm:table-cell', 'font-medium text-danger-600' => $overdue, 'text-slate-600' => ! $overdue])>{{ $task->due_date?->format('M j, Y') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{ $tasks->links() }}
                    </div>
                @endif
            </x-live-region>
        </div>
    </div>

    @include('tasks._panel-drawer')
</x-app-layout>
