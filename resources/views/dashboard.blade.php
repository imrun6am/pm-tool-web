@php
    use App\Support\Timeline;
    use Illuminate\Support\Str;
@endphp
<x-app-layout>
    @php
        // The timeline defaults to the current year + quarter; "Clear" returns there.
        $hasFilters = request()->hasAny(['year', 'quarter', 'project_id', 'team_id', 'group_by']);
        $canManage = auth()->user()->canManageReleases();

        $sameYear = $rangeStart->year === $rangeEnd->year;
        $rangeLabel = ($sameYear ? $rangeStart->format('M j') : $rangeStart->format('M j, Y')).' – '.$rangeEnd->format('M j, Y');

        // Same date→% math as the bars, so the marker lines up with them.
        $today = Timeline::segment(today(), today(), $rangeStart, $rangeEnd);
        $releaseCount = collect($groups)->sum(fn ($g) => count($g['bars']));
        $hasConflict = $analytics['conflictCount'] > 0;

        $statusFill = [
            'todo' => '#94a3b8',        // slate-400
            'in_progress' => '#3b82f6', // blue-500
            'in_review' => '#f59e0b',   // amber-500
            'recheck' => '#f97316',     // orange-500
            'done' => '#10b981',        // emerald-500
            'archive' => '#64748b',     // slate-500
        ];
    @endphp

    <x-slot name="header">
        <x-page-header title="Dashboard" :description="$analytics['periodLabel'].' · '.$rangeLabel">
            @if ($canManage)
                <x-slot name="actions">
                    <a href="{{ route('releases.create') }}" class="btn-primary btn-sm">
                        <x-icon name="plus" variant="outline" class="h-4 w-4" />
                        New release
                    </a>
                </x-slot>
            @endif
        </x-page-header>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container space-y-6">
            @include('partials.onboarding-checklist')

            {{-- Filters --}}
            <x-filter-bar :action="route('dashboard')" :active="$hasFilters">
                <label for="year" class="filter-label">Year</label>
                <select id="year" name="year" class="filter-select">
                    @foreach ($years as $y)
                        <option value="{{ $y }}" @selected($filters['year'] === $y)>{{ $y }}</option>
                    @endforeach
                </select>

                <label for="quarter" class="filter-label">Quarter</label>
                <select id="quarter" name="quarter" class="filter-select">
                    <option value="">All quarters</option>
                    @foreach ([1,2,3,4] as $q)
                        <option value="{{ $q }}" @selected($filters['quarter'] === $q)>Q{{ $q }}</option>
                    @endforeach
                </select>

                <label for="project_id" class="filter-label">Project</label>
                <select id="project_id" name="project_id" class="filter-select max-w-[13rem]">
                    <option value="">All projects</option>
                    @foreach ($projects as $project)
                        <option value="{{ $project->id }}" @selected($filters['project_id'] === $project->id)>{{ $project->name }}</option>
                    @endforeach
                </select>

                <label for="team_id" class="filter-label">Team</label>
                <select id="team_id" name="team_id" class="filter-select max-w-[13rem]">
                    <option value="">All teams</option>
                    @foreach ($teams as $team)
                        <option value="{{ $team->id }}" @selected($filters['team_id'] === $team->id)>{{ $team->name }}</option>
                    @endforeach
                </select>

                <fieldset class="segmented">
                    <legend class="sr-only">Group timeline by</legend>
                    <label><input type="radio" name="group_by" value="team" @checked($filters['group_by'] === 'team')>Team</label>
                    <label><input type="radio" name="group_by" value="project" @checked($filters['group_by'] === 'project')>Project</label>
                </fieldset>
            </x-filter-bar>

            {{-- Your own open work, for leads who also carry tasks --}}
            @if ($delivery['mine']['open'] > 0)
                @php $mine = $delivery['mine']; @endphp
                <a href="{{ route('board.index') }}" class="card flex flex-wrap items-center gap-x-4 gap-y-1 px-5 py-3 text-sm transition-colors hover:bg-slate-50 sm:px-6">
                    <span class="flex items-center gap-2 font-medium text-slate-900">
                        <x-icon name="clipboard-check" variant="outline" class="h-4 w-4 text-brand-600" /> Your work
                    </span>
                    <span class="text-slate-600"><span class="tabular font-semibold text-slate-900">{{ $mine['open'] }}</span> open</span>
                    <span @class(['text-slate-600', 'text-warning-800' => $mine['overdue'] > 0])><span class="tabular font-semibold">{{ $mine['overdue'] }}</span> overdue</span>
                    <span class="text-slate-600"><span class="tabular font-semibold text-slate-900">{{ $mine['dueSoon'] }}</span> due in the next {{ \App\Services\DeliveryAnalyticsService::DUE_SOON_DAYS }} days</span>
                    <span class="ml-auto inline-flex items-center gap-1 font-medium text-brand-700">Open board <x-icon name="arrow-right" variant="outline" class="h-4 w-4" /></span>
                </a>
            @endif

            {{-- ============================ KPI STRIP ============================ --}}
            <div class="stat-strip">
                <div class="stat-cell">
                    <p class="stat-label"><x-icon name="rocket" variant="outline" class="h-4 w-4 text-violet-600" />Active releases</p>
                    <p class="stat-value">{{ $analytics['active'] }}</p>
                    <p class="stat-sub">ongoing in {{ $analytics['periodLabel'] }}</p>
                </div>
                <div class="stat-cell">
                    <p class="stat-label"><x-icon name="check" variant="outline" class="h-4 w-4 text-emerald-600" />Completed</p>
                    <p class="stat-value">{{ $analytics['completedThisYear'] }}</p>
                    <p class="stat-sub">shipped in {{ $analytics['periodLabel'] }}</p>
                </div>
                <div class="stat-cell">
                    <p class="stat-label"><x-icon name="calendar" variant="outline" class="h-4 w-4 text-sky-600" />Starting soon</p>
                    <p class="stat-value">{{ $analytics['upcoming'] }}</p>
                    <p class="stat-sub">start in the next 30 days</p>
                </div>
                @if ($hasConflict)
                    <a href="#timeline" class="stat-cell group bg-warning-50/50 hover:!bg-warning-50" title="Jump to the timeline">
                        <p class="stat-label text-warning-800"><x-icon name="exclamation-circle" variant="outline" class="h-4 w-4 text-warning-600" />Scheduling conflicts</p>
                        <p class="stat-value text-warning-700">{{ $analytics['conflictCount'] }}</p>
                        <p class="stat-sub text-warning-800">{{ $analytics['teamsDoubleBooked'] }} {{ Str::plural('team', $analytics['teamsDoubleBooked']) }} double-booked</p>
                        <x-icon name="arrow-right" variant="outline" class="absolute right-5 top-4 h-4 w-4 text-warning-600 transition-transform group-hover:translate-x-0.5" />
                    </a>
                @else
                    <div class="stat-cell">
                        <p class="stat-label"><x-icon name="check" variant="outline" class="h-4 w-4 text-emerald-600" />Scheduling conflicts</p>
                        <p class="stat-value">0</p>
                        <p class="stat-sub">All teams clear</p>
                    </div>
                @endif
            </div>

            {{-- ============================ TIMELINE ============================ --}}
            <section id="timeline" class="card scroll-mt-24" aria-labelledby="timeline-title">
                <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-3 border-b border-slate-100 px-5 py-4 sm:px-6">
                    <div>
                        <h2 id="timeline-title" class="card-title">Release timeline</h2>
                        <p class="card-subtitle">
                            {{ $releaseCount }} {{ Str::plural('release', $releaseCount) }}
                            · grouped by {{ $filters['group_by'] === 'project' ? 'project' : 'team' }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-slate-600">
                        @foreach ($phaseLabels as $key => $label)
                            <span class="inline-flex items-center gap-1.5">
                                <span class="h-2.5 w-2.5 rounded-sm" style="background-color: {{ $phaseColors[$key] }}"></span>{{ $label }}
                            </span>
                        @endforeach
                        @if ($hasConflicts)
                            <span class="badge badge-warning">
                                <x-icon name="exclamation-circle" variant="outline" class="h-3.5 w-3.5" />
                                Amber outline = same team double-booked
                            </span>
                        @endif
                    </div>
                </div>

                @if (empty($groups))
                    <x-empty-state icon="rocket" title="No releases match this view."
                                   description="Try another quarter, project or team.">
                        @if ($hasFilters)
                            <a href="{{ route('dashboard') }}" class="btn-secondary btn-sm">Clear filters</a>
                        @endif
                        @if ($canManage)
                            <a href="{{ route('releases.create') }}" class="btn-primary btn-sm">
                                <x-icon name="plus" variant="outline" class="h-4 w-4" />Create one
                            </a>
                        @endif
                    </x-empty-state>
                @else
                    <div class="table-wrap">
                        <div class="min-w-[900px]">
                            {{-- Month axis --}}
                            <div class="flex border-b border-slate-100 bg-slate-50/80">
                                <div class="w-52 flex-none px-5 py-2 text-xs font-medium text-slate-500">
                                    {{ $filters['group_by'] === 'project' ? 'Project' : 'Team' }}
                                </div>
                                <div class="relative h-9 flex-1">
                                    @foreach ($months as $m)
                                        <div class="absolute top-0 h-9 border-l border-slate-200 px-2 text-xs font-medium leading-9 text-slate-500"
                                             style="left: {{ $m['offset'] }}%; width: {{ $m['width'] }}%">
                                            {{ $m['label'] }}
                                        </div>
                                    @endforeach
                                    @if ($today['visible'])
                                        <span class="absolute top-1/2 z-10 -translate-x-1/2 -translate-y-1/2 rounded-full bg-brand-600 px-1.5 py-0.5 text-[10px] font-semibold leading-none text-white shadow-sm ring-2 ring-white"
                                              style="left: {{ $today['offset'] }}%">Today</span>
                                    @endif
                                </div>
                            </div>

                            {{-- Rows --}}
                            @foreach ($groups as $group)
                                <div class="flex border-b border-slate-100 last:border-b-0">
                                    <div class="w-52 flex-none px-5 py-4">
                                        <a href="{{ $group['type'] === 'project' ? route('projects.show', $group['id']) : route('teams.show', $group['id']) }}"
                                           class="flex items-center gap-2.5 text-sm font-medium text-slate-800 hover:text-brand-700">
                                            <span class="flex h-6 w-6 flex-none items-center justify-center rounded-md text-[10px] font-bold text-white shadow-sm"
                                                  style="background-color: {{ $group['color'] }}">{{ mb_strtoupper(mb_substr($group['label'], 0, 1)) }}</span>
                                            <span class="truncate">{{ $group['label'] }}</span>
                                        </a>
                                        <p class="mt-1 pl-[2.125rem] text-xs text-slate-500">{{ count($group['bars']) }} {{ Str::plural('release', count($group['bars'])) }}</p>
                                    </div>

                                    <div class="relative flex-1 py-3 pr-4">
                                        {{-- faint month gridlines --}}
                                        @foreach ($months as $m)
                                            <div class="pointer-events-none absolute inset-y-0 border-l border-slate-100" style="left: {{ $m['offset'] }}%"></div>
                                        @endforeach
                                        @if ($today['visible'])
                                            <div class="pointer-events-none absolute inset-y-0 z-20 w-0.5 -translate-x-1/2 bg-brand-500/70" style="left: {{ $today['offset'] }}%" aria-hidden="true"></div>
                                        @endif

                                        <div class="space-y-3">
                                            @foreach ($group['bars'] as $bar)
                                                @php
                                                    $release = $bar['release'];
                                                    $barLeft = $bar['offset'];
                                                    $barWidth = max($bar['width'], 2);
                                                    $barEnd = min($barLeft + $barWidth, 100);
                                                    // Late-in-range bars anchor their caption to the right edge so
                                                    // the date text extends into free space instead of off-screen.
                                                    $anchorRight = $barEnd > 65;
                                                    $sameYearBar = $release->start_date->year === $release->end_date->year;
                                                    $startFmt = $sameYearBar ? $release->start_date->format('M j') : $release->start_date->format('M j, Y');
                                                    $dateRange = $startFmt.' – '.$release->end_date->format('M j, Y');
                                                @endphp
                                                <div class="relative">
                                                    <div class="relative h-8">
                                                        <a href="{{ route('releases.show', $release) }}"
                                                           class="group absolute top-0 flex h-8 items-center overflow-hidden rounded-lg shadow-sm transition hover:shadow-md hover:brightness-105 {{ $bar['conflict'] ? 'ring-2 ring-amber-500 ring-offset-2 ring-offset-white' : 'ring-1 ring-inset ring-black/5' }}"
                                                           style="left: {{ $barLeft }}%; width: {{ $barWidth }}%; background-color: #f1f5f9"
                                                           title="{{ $release->name }} · {{ $dateRange }}{{ $bar['conflict'] ? ' · OVERLAP' : '' }}">
                                                            {{-- phase segments fill the bar --}}
                                                            @foreach ($bar['phases'] as $phase)
                                                                <span class="absolute top-0 h-8"
                                                                      style="left: {{ $phase['offset'] }}%; width: {{ $phase['width'] }}%; background-color: {{ $phase['color'] }}"
                                                                      title="{{ $phase['label'] }}: {{ $phase['start']->format('M j') }}–{{ $phase['end']->format('M j') }}"></span>
                                                            @endforeach
                                                            <span class="relative z-10 truncate px-2.5 text-xs font-semibold text-white drop-shadow-sm">
                                                                {{ $release->name }}
                                                            </span>
                                                            @if ($bar['conflict'])
                                                                <span class="relative z-10 ml-auto mr-1.5 flex h-4 w-4 flex-none items-center justify-center rounded-full bg-white text-[10px] font-bold text-amber-700">!</span>
                                                            @endif
                                                        </a>
                                                    </div>
                                                    <div class="pointer-events-none relative mt-1 h-3.5">
                                                        <span class="tabular absolute top-0 whitespace-nowrap text-[11px] leading-none text-slate-500"
                                                              style="{{ $anchorRight ? 'right: '.(100 - $barEnd).'%' : 'left: '.$barLeft.'%' }}">
                                                            {{ $dateRange }}
                                                        </span>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </section>

            {{-- ============================ INSIGHTS ============================ --}}
            <div class="grid gap-6 lg:grid-cols-3">

                {{-- Delivery progress: donut of task statuses --}}
                <section class="card card-pad flex flex-col" aria-labelledby="progress-title">
                    <h2 id="progress-title" class="card-title">Delivery progress</h2>
                    <p class="card-subtitle">Task status across ongoing releases</p>

                    @if ($analytics['taskTotal'] > 0)
                        @php
                            $cursor = 0;
                            $summary = collect($analytics['statusCounts'])->map(fn ($c, $s) => $analytics['statusLabels'][$s].': '.$c)->implode(', ');
                        @endphp
                        <div class="mt-5 flex flex-1 items-center gap-6">
                            <div class="relative h-32 w-32 flex-none">
                                <svg viewBox="0 0 42 42" class="h-full w-full -rotate-90" role="img"
                                     aria-label="{{ $analytics['donePct'] }}% done. {{ $summary }}">
                                    <circle cx="21" cy="21" r="15.9155" fill="none" stroke="#f1f5f9" stroke-width="5" />
                                    @foreach ($analytics['statusCounts'] as $status => $count)
                                        @if ($count > 0)
                                            @php $pct = $count / $analytics['taskTotal'] * 100; @endphp
                                            <circle cx="21" cy="21" r="15.9155" fill="none" stroke-width="5"
                                                    stroke="{{ $statusFill[$status] }}"
                                                    stroke-dasharray="{{ round($pct, 3) }} {{ round(100 - $pct, 3) }}"
                                                    stroke-dashoffset="{{ round(-$cursor, 3) }}" />
                                            @php $cursor += $pct; @endphp
                                        @endif
                                    @endforeach
                                </svg>
                                <div class="absolute inset-0 flex flex-col items-center justify-center">
                                    <span class="tabular text-2xl font-semibold leading-none tracking-tight text-slate-900">{{ $analytics['donePct'] }}%</span>
                                    <span class="mt-1 text-[11px] text-slate-500">done</span>
                                </div>
                            </div>
                            <ul class="min-w-0 flex-1 space-y-1.5">
                                @foreach ($analytics['statusCounts'] as $status => $count)
                                    <li class="flex items-center gap-2 text-sm">
                                        <span class="h-2.5 w-2.5 flex-none rounded-full" style="background-color: {{ $statusFill[$status] }}"></span>
                                        <span class="truncate text-slate-600">{{ $analytics['statusLabels'][$status] }}</span>
                                        <span class="tabular ml-auto font-semibold text-slate-900">{{ $count }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                        <p class="mt-4 border-t border-slate-100 pt-3 text-xs text-slate-500">{{ $analytics['taskTotal'] }} {{ Str::plural('task', $analytics['taskTotal']) }} in total</p>
                    @else
                        <div class="flex flex-1 flex-col items-center justify-center py-6 text-center">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-50 text-slate-400 ring-1 ring-inset ring-slate-200">
                                <x-icon name="clipboard-check" variant="outline" class="h-5 w-5" />
                            </span>
                            <p class="mt-3 max-w-[16rem] text-sm text-slate-500">No tasks on the ongoing releases in this view yet.</p>
                        </div>
                    @endif
                </section>

                {{-- Release load by month --}}
                <section class="card card-pad flex flex-col" aria-labelledby="load-title">
                    <h2 id="load-title" class="card-title">Release load</h2>
                    <p class="card-subtitle">Releases in flight each month · {{ $analytics['periodLabel'] }}</p>

                    @php $mMax = max($analytics['monthlyMax'], 1); @endphp
                    <div class="mt-5 flex flex-1 flex-col">
                        <div class="relative flex h-40 items-end gap-2">
                            {{-- gridlines --}}
                            @foreach ([0, 50, 100] as $line)
                                <div class="pointer-events-none absolute inset-x-0 border-t border-dashed border-slate-200" style="bottom: {{ $line * 0.85 }}%"></div>
                            @endforeach
                            @foreach ($analytics['monthly'] as $m)
                                <div class="relative flex h-full flex-1 flex-col items-center justify-end gap-1"
                                     title="{{ $m['label'] }}: {{ $m['count'] }} {{ Str::plural('release', $m['count']) }}">
                                    @if ($m['count'] > 0)
                                        <span class="tabular text-[11px] font-semibold {{ $m['current'] ? 'text-brand-700' : 'text-slate-500' }}">{{ $m['count'] }}</span>
                                    @endif
                                    <div class="w-full max-w-[2.25rem] rounded-t-md transition-colors {{ $m['current'] ? 'bg-brand-600' : 'bg-brand-300 hover:bg-brand-400' }}"
                                         style="height: {{ $m['count'] > 0 ? max((int) round($m['count'] / $mMax * 85), 6) : 0 }}%"></div>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-2 flex gap-2 border-t border-slate-200 pt-2">
                            @foreach ($analytics['monthly'] as $m)
                                <div class="flex-1 text-center text-[11px] {{ $m['current'] ? 'font-semibold text-brand-700' : 'text-slate-500' }}">{{ $m['label'] }}</div>
                            @endforeach
                        </div>
                    </div>
                </section>

                {{-- Team workload --}}
                <section class="card card-pad flex flex-col" aria-labelledby="workload-title">
                    <h2 id="workload-title" class="card-title">Team workload</h2>
                    <p class="card-subtitle">Active releases per team</p>

                    @if (empty($analytics['teamWorkload']))
                        <div class="flex flex-1 flex-col items-center justify-center py-6 text-center">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-50 text-slate-400 ring-1 ring-inset ring-slate-200">
                                <x-icon name="user-group" variant="outline" class="h-5 w-5" />
                            </span>
                            <p class="mt-3 text-sm text-slate-500">No active releases in this view.</p>
                        </div>
                    @else
                        @php $twMax = max($analytics['teamWorkloadMax'], 1); @endphp
                        <ul class="mt-5 space-y-3.5">
                            @foreach (array_slice($analytics['teamWorkload'], 0, 6) as $t)
                                <li>
                                    <div class="flex items-center justify-between gap-3 text-sm">
                                        <span class="flex min-w-0 items-center gap-2">
                                            <span class="h-2.5 w-2.5 flex-none rounded-full" style="background-color: {{ $t['color'] }}"></span>
                                            <span class="truncate text-slate-700" title="{{ $t['name'] }}">{{ $t['name'] }}</span>
                                        </span>
                                        <span class="tabular flex-none font-semibold text-slate-900">{{ $t['count'] }}</span>
                                    </div>
                                    <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100">
                                        <div class="h-full rounded-full" style="width: {{ max((int) round($t['count'] / $twMax * 100), 6) }}%; background-color: {{ $t['color'] }}"></div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                        @if (count($analytics['teamWorkload']) > 6)
                            <p class="mt-4 text-xs text-slate-500">+{{ count($analytics['teamWorkload']) - 6 }} more {{ Str::plural('team', count($analytics['teamWorkload']) - 6) }}</p>
                        @endif
                    @endif
                </section>
            </div>

            @include('partials.delivery-health', ['d' => $delivery])
        </div>
    </div>
</x-app-layout>
