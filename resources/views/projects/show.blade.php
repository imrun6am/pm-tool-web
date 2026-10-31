<x-app-layout>
    <x-slot name="header">
        <div class="space-y-3">
            {{-- Breadcrumb --}}
            <nav class="flex items-center gap-1.5 text-xs text-slate-400">
                <a href="{{ route('projects.index') }}" class="hover:text-brand-600">Projects</a>
                <span>/</span>
                <span class="font-medium text-slate-600">{{ $project->name }}</span>
            </nav>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="inline-block h-5 w-5 flex-none rounded-full ring-2 ring-white ring-offset-1 ring-offset-slate-200" style="background-color: {{ $project->color }}"></span>
                    <h2 class="page-title">{{ $project->name }}</h2>
                    @if ($project->isArchived())
                        <span class="badge bg-slate-200 text-slate-700">Archived</span>
                    @else
                        <span class="badge bg-emerald-100 text-emerald-700">Active</span>
                    @endif
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('dashboard', ['project_id' => $project->id]) }}" class="btn-secondary btn-sm">
                        <x-icon name="calendar" class="h-4 w-4" />
                        Timeline
                    </a>
                    @if (auth()->user()->canManageWorkspace())
                        <a href="{{ route('projects.edit', $project) }}" class="btn-secondary btn-sm">
                            <x-icon name="pencil" class="h-4 w-4" />
                            Edit
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </x-slot>

    @php
        // Shared task-status palette (matches the planning dashboard).
        $statusFill = [
            'todo' => '#94a3b8',        // slate-400
            'in_progress' => '#3b82f6', // blue-500
            'in_review' => '#f59e0b',   // amber-500
            'recheck' => '#f97316',     // orange-500
            'done' => '#10b981',        // emerald-500
            'archive' => '#64748b',     // slate-500
        ];
    @endphp

    <div class="py-8">
        <div class="app-container space-y-6">

            {{-- ============================ OVERVIEW (HERO) ============================ --}}
            <div class="card card-pad">
                <div class="grid gap-6 md:grid-cols-3">
                    {{-- Description --}}
                    <div class="md:col-span-2">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400">About</h3>
                        @if ($project->description)
                            <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ $project->description }}</p>
                        @else
                            <p class="mt-2 text-sm italic text-slate-400">No description provided for this project.</p>
                        @endif
                    </div>

                    {{-- Key facts --}}
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-4 md:border-l md:border-slate-100 md:pl-6">
                        <div>
                            <dt class="text-xs font-medium text-slate-400">Status</dt>
                            <dd class="mt-1">
                                @if ($project->isArchived())
                                    <span class="badge bg-slate-200 text-slate-700">Archived</span>
                                @else
                                    <span class="badge bg-emerald-100 text-emerald-700">Active</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-slate-400">Teams involved</dt>
                            <dd class="mt-1 text-sm font-semibold text-slate-800 tabular">{{ $stats['teamsInvolved'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-slate-400">Timeframe</dt>
                            <dd class="mt-1 text-sm font-semibold text-slate-800">
                                @if ($stats['spanStart'] && $stats['spanEnd'])
                                    {{ $stats['spanStart']->format('M Y') }} – {{ $stats['spanEnd']->format('M Y') }}
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-slate-400">Created</dt>
                            <dd class="mt-1 text-sm font-semibold text-slate-800">{{ $project->created_at?->format('M j, Y') ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- ============================ KPI TILES ============================ --}}
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                @include('partials.stat-tile', [
                    'label' => 'Releases',
                    'value' => $stats['releaseTotal'],
                    'sub' => $stats['upcoming'] > 0 ? $stats['upcoming'].' starting soon' : 'total',
                    'icon' => 'rocket',
                    'tone' => 'brand',
                ])
                @include('partials.stat-tile', [
                    'label' => 'Ongoing',
                    'value' => $stats['ongoing'],
                    'sub' => 'in flight',
                    'icon' => 'activity',
                    'tone' => 'sky',
                ])
                @include('partials.stat-tile', [
                    'label' => 'Completed',
                    'value' => $stats['completed'],
                    'sub' => $stats['completionPct'].'% of releases',
                    'icon' => 'archive',
                    'tone' => 'emerald',
                ])
                @include('partials.stat-tile', [
                    'label' => 'Open tasks',
                    'value' => $stats['openTasks'],
                    'sub' => $stats['overdue'] > 0 ? $stats['overdue'].' overdue' : 'nothing overdue',
                    'icon' => 'list',
                    'tone' => $stats['overdue'] > 0 ? 'rose' : 'slate',
                ])
            </div>

            {{-- ==================== TWO-COLUMN: MAIN + SIDEBAR ==================== --}}
            <div class="grid gap-6 lg:grid-cols-3 lg:items-start">

                {{-- ============================ MAIN (LEFT) ============================ --}}
                <div class="space-y-6 lg:col-span-2">

                    {{-- Delivery progress --}}
                    <div class="card card-pad">
                        <div class="grid gap-6 sm:grid-cols-3">
                            {{-- Release completion --}}
                            <div class="sm:border-r sm:border-slate-100 sm:pr-6">
                                <h3 class="text-sm font-semibold text-slate-900">Release completion</h3>
                                <p class="mt-0.5 text-xs text-slate-400">Shipped vs. planned</p>
                                <div class="mt-4 flex items-baseline gap-1.5">
                                    <span class="text-3xl font-semibold tracking-tight text-slate-900 tabular">{{ $stats['completionPct'] }}%</span>
                                    <span class="text-xs text-slate-400">{{ $stats['completed'] }}/{{ $stats['releaseTotal'] }}</span>
                                </div>
                                <div class="mt-3 h-2.5 w-full overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-emerald-500 transition-all" style="width: {{ $stats['completionPct'] }}%"></div>
                                </div>
                            </div>

                            {{-- Task delivery --}}
                            <div class="sm:col-span-2">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h3 class="text-sm font-semibold text-slate-900">Task delivery</h3>
                                        <p class="mt-0.5 text-xs text-slate-400">Status mix across all releases</p>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-2xl font-semibold tracking-tight text-slate-900 tabular">{{ $stats['donePct'] }}%</span>
                                        <span class="ml-1 text-xs text-slate-400">done · {{ $stats['taskTotal'] }} {{ \Illuminate\Support\Str::plural('task', $stats['taskTotal']) }}</span>
                                    </div>
                                </div>

                                @if ($stats['taskTotal'] > 0)
                                    <div class="mt-4 flex h-2.5 w-full gap-[2px]">
                                        @foreach ($stats['statusCounts'] as $status => $count)
                                            @if ($count > 0)
                                                <div class="rounded-[3px] first:rounded-l-full last:rounded-r-full"
                                                     style="width: {{ $count / $stats['taskTotal'] * 100 }}%; background-color: {{ $statusFill[$status] }}"
                                                     title="{{ $stats['statusLabels'][$status] }}: {{ $count }}"></div>
                                            @endif
                                        @endforeach
                                    </div>
                                    <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1.5">
                                        @foreach ($stats['statusCounts'] as $status => $count)
                                            <span class="inline-flex items-center gap-1.5 text-xs">
                                                <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $statusFill[$status] }}"></span>
                                                <span class="text-slate-600">{{ $stats['statusLabels'][$status] }}</span>
                                                <span class="font-semibold text-slate-900 tabular">{{ $count }}</span>
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="mt-4 text-sm text-slate-400">No tasks have been created on this project's releases yet.</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Release cadence by quarter --}}
                    <div class="card card-pad">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900">Release cadence</h3>
                                <p class="mt-0.5 text-xs text-slate-400">Releases per quarter</p>
                            </div>
                            <div class="flex items-center gap-3 text-[11px] text-slate-500">
                                <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-sm bg-brand-400"></span>Ongoing</span>
                                <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-sm bg-emerald-500"></span>Completed</span>
                            </div>
                        </div>

                        @if (empty($stats['byPeriod']))
                            <p class="mt-6 text-sm text-slate-400">No releases scheduled yet.</p>
                        @else
                            @php $pMax = $stats['periodMax']; @endphp
                            <div class="mt-6">
                                <div class="flex h-40 items-end gap-2">
                                    @foreach ($stats['byPeriod'] as $p)
                                        @php
                                            $barH = $pMax > 0 ? max((int) round($p['count'] / $pMax * 88), $p['count'] > 0 ? 10 : 0) : 0;
                                            $donePart = $p['count'] > 0 ? $p['completed'] / $p['count'] * 100 : 0;
                                        @endphp
                                        <div class="flex h-full flex-1 flex-col items-center justify-end gap-1"
                                             title="{{ $p['label'] }} {{ $p['year'] }}: {{ $p['count'] }} {{ \Illuminate\Support\Str::plural('release', $p['count']) }} ({{ $p['completed'] }} completed)">
                                            <span class="text-[10px] font-semibold text-slate-500 tabular">{{ $p['count'] }}</span>
                                            <div class="relative flex w-full max-w-[2.5rem] flex-col justify-end overflow-hidden rounded-t-[4px] {{ $p['current'] ? 'ring-2 ring-brand-300' : '' }}"
                                                 style="height: {{ $barH }}%">
                                                <div class="w-full bg-brand-400" style="height: {{ 100 - $donePart }}%"></div>
                                                <div class="w-full bg-emerald-500" style="height: {{ $donePart }}%"></div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="mt-2 flex gap-2">
                                    @foreach ($stats['byPeriod'] as $p)
                                        <div class="flex-1 text-center leading-tight">
                                            <div class="text-[11px] {{ $p['current'] ? 'font-semibold text-brand-600' : 'text-slate-500' }}">{{ $p['label'] }}</div>
                                            <div class="text-[10px] text-slate-300 tabular">'{{ substr((string) $p['year'], -2) }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Releases table --}}
                    <div class="card overflow-hidden">
                        <div class="card-header">
                            <h3 class="text-sm font-semibold text-slate-700">Releases <span class="text-slate-400">({{ $releases->count() }})</span></h3>
                            @if (auth()->user()->canManageReleases())
                                <a href="{{ route('releases.create') }}" class="btn-secondary btn-sm">
                                    <x-icon name="plus" class="h-4 w-4" />
                                    New release
                                </a>
                            @endif
                        </div>

                        @if ($releases->isEmpty())
                            <div class="p-12 text-center">
                                <x-icon name="rocket" class="mx-auto h-10 w-10 text-slate-300" />
                                <p class="mt-3 text-sm text-slate-500">No releases in this project yet.</p>
                            </div>
                        @else
                            <div class="overflow-x-auto">
                                <table class="table-base">
                                    <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                                        <tr>
                                            <th class="px-6 py-3">Release</th>
                                            <th class="px-6 py-3">Team</th>
                                            <th class="px-6 py-3">Quarter</th>
                                            <th class="px-6 py-3">Window</th>
                                            <th class="px-6 py-3 w-48">Progress</th>
                                            <th class="px-6 py-3">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @foreach ($releases as $release)
                                            @php
                                                $total = $release->tasks_count;
                                                $done = $release->done_tasks_count;
                                                $pct = $total > 0 ? (int) round($done / $total * 100) : 0;
                                            @endphp
                                            <tr class="{{ $release->isComplete() ? 'bg-emerald-50/40' : '' }}">
                                                <td class="px-6 py-4">
                                                    <a href="{{ route('releases.show', $release) }}" class="font-medium text-slate-900 hover:text-brand-600">{{ $release->name }}</a>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <span class="inline-flex items-center gap-2 text-slate-600">
                                                        <span class="h-2.5 w-2.5 flex-none rounded-full" style="background-color: {{ $release->team->color }}"></span>
                                                        {{ $release->team->name }}
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 text-slate-600 tabular">{{ $release->year }} · {{ $release->quarterLabel() }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-slate-600 tabular">{{ $release->start_date->format('M j') }} – {{ $release->end_date->format('M j, Y') }}</td>
                                                <td class="px-6 py-4">
                                                    @if ($total > 0)
                                                        <div class="flex items-center gap-2">
                                                            <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100">
                                                                <div class="h-full rounded-full {{ $pct === 100 ? 'bg-emerald-500' : 'bg-brand-500' }}" style="width: {{ $pct }}%"></div>
                                                            </div>
                                                            <span class="w-14 flex-none text-right text-xs font-medium text-slate-500 tabular">{{ $done }}/{{ $total }}</span>
                                                        </div>
                                                    @else
                                                        <span class="text-xs text-slate-400">No tasks</span>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4">
                                                    @if ($release->isComplete())
                                                        <span class="badge bg-emerald-100 text-emerald-700">Completed</span>
                                                    @else
                                                        <span class="badge bg-sky-100 text-sky-700">Ongoing</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- ============================ SIDEBAR (RIGHT) ============================ --}}
                <div class="space-y-6">

                    {{-- Releases by team --}}
                    @include('partials.hbar-chart', [
                        'title' => 'Releases by team',
                        'subtitle' => 'Ownership, busiest first',
                        'rows' => $stats['byTeam'],
                        'emptyText' => 'No releases assigned to a team yet.',
                    ])

                    {{-- Recent activity --}}
                    <div class="card overflow-hidden">
                        <div class="card-header">
                            <h3 class="text-sm font-semibold text-slate-700">Recent activity</h3>
                            <x-icon name="activity" class="h-4 w-4 text-slate-400" />
                        </div>
                        <div class="divide-y divide-slate-100 px-5 py-1 sm:px-6">
                            @include('partials.activity-list', ['activities' => $activities])
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
