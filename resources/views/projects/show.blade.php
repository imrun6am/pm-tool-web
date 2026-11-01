@php
    $me = auth()->user();
    $canManage = $me->canManageWorkspace();
    $canPlan = $me->canManageReleases();
    $archived = $project->isArchived();
    $conflictCount = count(array_filter($conflicts));
    $releaseWord = fn (int $n) => $n.' '.Str::plural('release', $n);
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$project->name"
                       :description="$project->description ? Str::limit($project->description, 180) : null"
                       :breadcrumbs="['Projects' => route('projects.index'), $project->name => null]">
            <x-slot name="actions">
                <a href="{{ route('dashboard', ['project_id' => $project->id]) }}" class="btn-secondary btn-sm">
                    <x-icon name="calendar" variant="outline" class="h-4 w-4" />
                    Timeline
                </a>
                @if ($canManage)
                    <a href="{{ route('projects.edit', $project) }}" class="btn-secondary btn-sm">
                        <x-icon name="pencil-square" variant="outline" class="h-4 w-4" />
                        Edit
                    </a>
                    @if ($archived)
                        <form method="POST" action="{{ route('projects.restore', $project) }}">@csrf
                            <button type="submit" class="btn-secondary btn-sm">
                                <x-icon name="restore" class="h-4 w-4" />Restore
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('projects.archive', $project) }}">@csrf
                            <button type="submit" class="btn-secondary btn-sm" title="Hide from planning; history is kept">
                                <x-icon name="archive" class="h-4 w-4" />Archive
                            </button>
                        </form>
                    @endif
                    @if ($deletable)
                        <form method="POST" action="{{ route('projects.destroy', $project) }}"
                              data-confirm="Delete this project permanently?" data-confirm-verb="Delete">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn-danger btn-sm">
                                <x-icon name="trash" variant="outline" class="h-4 w-4" />Delete
                            </button>
                        </form>
                    @endif
                @endif
            </x-slot>
        </x-page-header>

        {{-- At a glance --}}
        <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
            <span class="badge badge-slate">
                <span class="h-2 w-2 rounded-full" style="background-color: {{ $project->color }}" aria-hidden="true"></span>
                Project
            </span>
            @if ($archived)
                <span class="badge badge-slate"><x-icon name="lock-closed" variant="outline" class="h-3 w-3" />Archived</span>
            @else
                <span class="badge badge-success">Active</span>
            @endif
            @if ($stats['spanStart'] && $stats['spanEnd'])
                <span class="badge badge-slate tabular">
                    <x-icon name="calendar" variant="outline" class="h-3.5 w-3.5" />
                    {{ $stats['spanStart']->format('M Y') }} – {{ $stats['spanEnd']->format('M Y') }}
                </span>
            @endif
            @if ($stats['activeReleases'] > 0)
                <span class="badge badge-brand">{{ $stats['activeReleases'] }} in progress</span>
            @endif
            @if ($stats['overdueReleases'] > 0)
                <span class="badge badge-warning">{{ $releaseWord($stats['overdueReleases']) }} past window</span>
            @endif
            @if ($conflictCount > 0)
                <span class="badge badge-warning">
                    <x-icon name="exclamation-circle" variant="outline" class="h-3.5 w-3.5" />{{ $conflictCount }} double-booked
                </span>
            @endif
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container space-y-6">

            @if ($archived)
                <div class="card flex flex-wrap items-center justify-between gap-3 border-slate-200 bg-slate-50 px-5 py-3.5 sm:px-6">
                    <p class="flex items-start gap-2.5 text-sm text-slate-600">
                        <x-icon name="lock-closed" variant="outline" class="mt-0.5 h-4 w-4 flex-none text-slate-400" />
                        <span>This project is archived{{ $project->archived_at ? ' since '.$project->archived_at->format('M j, Y') : '' }}. It is hidden from release planning; its releases and history are kept.</span>
                    </p>
                    @if ($canManage)
                        <form method="POST" action="{{ route('projects.restore', $project) }}">@csrf
                            <button type="submit" class="btn-secondary btn-sm"><x-icon name="restore" class="h-4 w-4" />Restore project</button>
                        </form>
                    @endif
                </div>
            @endif

            {{-- ============================ KPI STRIP ============================ --}}
            <div class="stat-strip lg:grid-cols-5 max-lg:[&>*:nth-child(-n+4)]:border-b">
                <x-stat label="Releases" :value="$stats['releaseTotal']"
                        :sub="$stats['ongoing'].' ongoing · '.$stats['completed'].' completed'"
                        icon="rocket" tone="releases" />
                <x-stat label="Tasks done" :value="$stats['doneTasks'].'/'.$stats['taskTotal']"
                        :sub="$stats['taskTotal'] > 0 ? $stats['donePct'].'% complete' : 'no tasks yet'"
                        icon="check" tone="success" />
                <x-stat label="Open tasks" :value="$stats['openTasks']" sub="not done yet"
                        icon="clipboard-check" tone="info" />
                <x-stat label="Overdue" :value="$stats['overdue']"
                        :sub="$stats['overdue'] > 0 ? 'open past their due date' : 'nothing overdue'"
                        icon="exclamation-circle" tone="danger" :alert="$stats['overdue'] > 0" />
                <x-stat label="Teams involved" :value="$stats['teamsInvolved']" sub="owning releases"
                        icon="user-group" tone="teams" class="col-span-2 max-lg:!border-r-0 lg:col-span-1" />
            </div>

            {{-- ==================== MAIN + SIDEBAR ==================== --}}
            <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem] xl:grid-cols-[minmax(0,1fr)_24rem]">

                <div class="min-w-0 space-y-6">
                    @if ($releases->isEmpty())
                        <div class="card">
                            <x-empty-state icon="rocket" title="No releases in this project yet."
                                           description="Plan a release window to start tracking work here.">
                                @if ($canPlan && ! $archived)
                                    <a href="{{ route('releases.create') }}" class="btn-primary btn-sm">
                                        <x-icon name="plus" variant="outline" class="h-4 w-4" />New release
                                    </a>
                                @endif
                            </x-empty-state>
                        </div>
                    @else
                        {{-- Releases over time --}}
                        <section class="card overflow-hidden" aria-labelledby="timeline-title">
                            <div class="card-header flex-wrap">
                                <div class="min-w-0">
                                    <h2 id="timeline-title" class="card-title">Releases over time</h2>
                                    <p class="card-subtitle">Each release window, colored by its owning team</p>
                                </div>
                                @if ($conflictCount > 0)
                                    <span class="badge badge-warning" title="Releases whose team is booked on another release of this project at the same time">
                                        <x-icon name="exclamation-circle" variant="outline" class="h-3.5 w-3.5" />{{ $conflictCount }} double-booked
                                    </span>
                                @endif
                            </div>
                            <div class="px-5 py-4 sm:px-6">
                                <x-release-timeline :releases="$releases" :conflicts="$conflicts" label="project" />
                            </div>
                            @if (count($stats['teamRows']) > 1)
                                <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 border-t border-slate-100 px-5 py-3 text-xs text-slate-600 sm:px-6">
                                    @foreach ($stats['teamRows'] as $row)
                                        <x-color-chip :color="$row['team']->color" :label="$row['team']->name" />
                                    @endforeach
                                </div>
                            @endif
                        </section>

                        {{-- Releases table --}}
                        <section class="card overflow-hidden" aria-labelledby="releases-title">
                            <div class="card-header">
                                <div class="min-w-0">
                                    <h2 id="releases-title" class="card-title">Releases <span class="font-normal text-slate-400">({{ $releases->count() }})</span></h2>
                                    <p class="card-subtitle">Window, state and counted-task progress</p>
                                </div>
                                @if ($canPlan && ! $archived)
                                    <a href="{{ route('releases.create') }}" class="btn-secondary btn-sm flex-none">
                                        <x-icon name="plus" variant="outline" class="h-4 w-4" />
                                        New release
                                    </a>
                                @endif
                            </div>
                            <div class="table-wrap">
                                <table class="table-base">
                                    <thead>
                                        <tr>
                                            <th>Release</th>
                                            <th>Team</th>
                                            <th>Window</th>
                                            <th>State</th>
                                            <th class="w-44">Progress</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($releases as $release)
                                            @php $conflict = $conflicts[$release->id] ?? false; @endphp
                                            <tr @class(['bg-warning-50/40' => $conflict])>
                                                <td>
                                                    <a href="{{ route('releases.show', $release) }}" class="whitespace-nowrap font-medium text-slate-900 hover:text-brand-700">{{ $release->name }}</a>
                                                    <p class="tabular text-xs text-slate-500">{{ $release->year }} · {{ $release->quarterLabel() }}</p>
                                                </td>
                                                <td>
                                                    <x-color-chip :color="$release->team->color" :label="$release->team->name"
                                                                  :href="route('teams.show', $release->team)" class="text-slate-600" />
                                                </td>
                                                <td class="whitespace-nowrap">
                                                    <span class="tabular text-slate-700">{{ $release->start_date->format('M j') }} – {{ $release->end_date->format('M j, Y') }}</span>
                                                    <p class="tabular text-xs text-slate-500">{{ $release->durationInDays() }} days</p>
                                                </td>
                                                <td>
                                                    <div class="flex flex-wrap items-center gap-1.5">
                                                        <x-release-state :release="$release" />
                                                        @if ($conflict)
                                                            <span class="badge badge-warning" title="Its team has another release of this project in the same window">
                                                                <x-icon name="exclamation-circle" variant="outline" class="h-3 w-3" />Overlap
                                                            </span>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td>
                                                    <x-progress :done="$release->done_tasks_count" :total="$release->tasks_count" class="min-w-[9rem]" />
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    @endif
                </div>

                {{-- ============================ SIDEBAR ============================ --}}
                <aside class="min-w-0 space-y-6">
                    {{-- About --}}
                    <section class="card card-pad" aria-labelledby="about-title">
                        <h2 id="about-title" class="card-title">About</h2>
                        @if ($project->description)
                            <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-slate-600">{{ $project->description }}</p>
                        @else
                            <p class="mt-2 text-sm italic text-slate-400">No description provided for this project.</p>
                        @endif
                        <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 border-t border-slate-100 pt-4 text-sm">
                            <div>
                                <dt class="text-xs text-slate-500">Status</dt>
                                <dd class="mt-0.5 font-medium text-slate-800">{{ $archived ? 'Archived' : 'Active' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-500">Releases shipped</dt>
                                <dd class="tabular mt-0.5 font-medium text-slate-800">{{ $stats['completed'] }}/{{ $stats['releaseTotal'] }} <span class="font-normal text-slate-500">({{ $stats['completionPct'] }}%)</span></dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-500">Timeframe</dt>
                                <dd class="tabular mt-0.5 font-medium text-slate-800">
                                    @if ($stats['spanStart'] && $stats['spanEnd'])
                                        {{ $stats['spanStart']->format('M Y') }} – {{ $stats['spanEnd']->format('M Y') }}
                                    @else
                                        —
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-500">Created</dt>
                                <dd class="tabular mt-0.5 font-medium text-slate-800">{{ $project->created_at?->format('M j, Y') ?? '—' }}</dd>
                            </div>
                        </dl>
                    </section>

                    {{-- Teams involved --}}
                    <section class="card overflow-hidden" aria-labelledby="teams-title">
                        <div class="card-header">
                            <div>
                                <h2 id="teams-title" class="card-title">Teams involved</h2>
                                <p class="card-subtitle">Releases owned and task progress</p>
                            </div>
                            <span class="badge badge-slate tabular">{{ $stats['teamsInvolved'] }}</span>
                        </div>
                        @if (empty($stats['teamRows']))
                            <p class="px-5 py-6 text-center text-sm text-slate-400 sm:px-6">No team owns a release here yet.</p>
                        @else
                            <ul class="divide-y divide-slate-100" role="list">
                                @foreach ($stats['teamRows'] as $row)
                                    <li class="px-5 py-3 sm:px-6">
                                        <div class="flex items-center justify-between gap-3">
                                            <x-color-chip :color="$row['team']->color" :label="$row['team']->name"
                                                          :href="route('teams.show', $row['team'])" class="text-sm font-medium text-slate-800" />
                                            <span class="tabular flex-none text-xs text-slate-500">
                                                {{ $releaseWord($row['releases']) }}{{ $row['ongoing'] > 0 ? ' · '.$row['ongoing'].' ongoing' : '' }}
                                            </span>
                                        </div>
                                        <x-progress :done="$row['done']" :total="$row['total']" class="mt-2" />
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>

                    {{-- Recent activity --}}
                    <section class="card overflow-hidden" aria-labelledby="activity-title">
                        <div class="card-header">
                            <h2 id="activity-title" class="card-title">Recent activity</h2>
                            <x-icon name="activity" class="h-4 w-4 text-slate-400" />
                        </div>
                        <div class="divide-y divide-slate-100 px-5 py-1 sm:px-6">
                            @include('partials.activity-list', ['activities' => $activities])
                        </div>
                    </section>
                </aside>
            </div>

            {{-- ==================== PROGRESS AND STATUS MIX ==================== --}}
            <section aria-labelledby="progress-title">
                <div class="mb-3">
                    <h2 id="progress-title" class="text-base font-semibold text-slate-900">Progress and status mix</h2>
                    <p class="text-sm text-slate-500">Counted tasks across every release in this project; archived tasks are left out.</p>
                </div>
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
                    {{-- Task status mix --}}
                    <div class="card card-pad">
                        <h3 class="card-title">Task status mix</h3>
                        <p class="card-subtitle">{{ $stats['taskTotal'] }} {{ Str::plural('task', $stats['taskTotal']) }} · {{ $stats['donePct'] }}% done</p>
                        <div class="mt-5">
                            @if ($stats['taskTotal'] > 0)
                                @include('partials.donut', [
                                    'label' => 'Tasks by status, '.$stats['donePct'].'% done',
                                    'center' => $stats['donePct'].'%',
                                    'caption' => 'done',
                                    'segments' => collect($stats['statusCounts'])->map(fn ($count, $status) => [
                                        'label' => $stats['statusLabels'][$status],
                                        'value' => $count,
                                        'color' => $stats['statusColors'][$status],
                                    ])->values()->all(),
                                ])
                            @else
                                <p class="py-6 text-center text-sm text-slate-400">No tasks have been created on this project's releases yet.</p>
                            @endif
                        </div>
                    </div>

                    {{-- Release cadence --}}
                    <div class="card card-pad">
                        <h3 class="card-title">Release cadence</h3>
                        <p class="card-subtitle">Releases per quarter · {{ $stats['completed'] }} of {{ $stats['releaseTotal'] }} shipped</p>
                        @if (empty($stats['byPeriod']))
                            <p class="py-6 text-center text-sm text-slate-400">No releases scheduled yet.</p>
                        @else
                            @include('partials.column-chart', [
                                'label' => 'Releases per quarter',
                                'bars' => collect($stats['byPeriod'])->map(fn ($p) => [
                                    'label' => $p['label']." '".substr((string) $p['year'], -2),
                                    'value' => $p['count'],
                                    'current' => $p['current'],
                                    'title' => $p['label'].' '.$p['year'].': '.$releaseWord($p['count']).' ('.$p['completed'].' completed)'.($p['current'] ? ' · this quarter' : ''),
                                ])->all(),
                            ])
                            <p class="mt-3 text-xs text-slate-500">The darker bar is the current quarter. Hover a bar for its shipped count.</p>
                        @endif
                    </div>

                    {{-- Releases by team --}}
                    <div class="md:col-span-2 xl:col-span-1">
                        @include('partials.hbar-chart', [
                            'title' => 'Releases by team',
                            'subtitle' => 'Ownership, busiest first',
                            'rows' => $stats['byTeam'],
                            'emptyText' => 'No releases assigned to a team yet.',
                        ])
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
