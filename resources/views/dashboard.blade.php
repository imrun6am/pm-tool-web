<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="page-title">Release timeline</h2>
            @if (auth()->user()->canManageReleases())
                <a href="{{ route('releases.create') }}"
                   class="btn-primary btn-sm">
                    New release
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="app-container space-y-6">

            {{-- Filters --}}
            <form method="GET" action="{{ route('dashboard') }}" class="card p-4">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
                    <div>
                        <label class="block text-xs font-medium text-slate-500">Year</label>
                        <select name="year" class="field-input">
                            @foreach ($years as $y)
                                <option value="{{ $y }}" @selected($filters['year'] === $y)>{{ $y }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500">Quarter</label>
                        <select name="quarter" class="field-input">
                            <option value="">All quarters</option>
                            @foreach ([1,2,3,4] as $q)
                                <option value="{{ $q }}" @selected($filters['quarter'] === $q)>Q{{ $q }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500">Project</label>
                        <select name="project_id" class="field-input">
                            <option value="">All projects</option>
                            @foreach ($projects as $project)
                                <option value="{{ $project->id }}" @selected($filters['project_id'] === $project->id)>{{ $project->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500">Team</label>
                        <select name="team_id" class="field-input">
                            <option value="">All teams</option>
                            @foreach ($teams as $team)
                                <option value="{{ $team->id }}" @selected($filters['team_id'] === $team->id)>{{ $team->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500">Group by</label>
                        <select name="group_by" class="field-input">
                            <option value="team" @selected($filters['group_by'] === 'team')>Team</option>
                            <option value="project" @selected($filters['group_by'] === 'project')>Project</option>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button class="w-full btn-primary btn-sm">Apply</button>
                        <a href="{{ route('dashboard') }}" class="btn-secondary btn-sm">Reset</a>
                    </div>
                </div>
            </form>

            {{-- ============================ OVERVIEW ============================ --}}
            @php
                $statusFill = [
                    'todo' => '#94a3b8',        // slate-400
                    'in_progress' => '#3b82f6', // blue-500
                    'in_review' => '#f59e0b',   // amber-500
                    'recheck' => '#f97316',     // orange-500
                    'done' => '#10b981',        // emerald-500
                    'archive' => '#64748b',     // slate-500
                ];
            @endphp

            {{-- KPI tiles --}}
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                {{-- Active releases --}}
                <div class="card card-pad">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-slate-500">Active releases</span>
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M15.5 2A1.5 1.5 0 0014 3.5v13a1.5 1.5 0 001.5 1.5h1a1.5 1.5 0 001.5-1.5v-13A1.5 1.5 0 0016.5 2h-1zM9.5 6A1.5 1.5 0 008 7.5v9A1.5 1.5 0 009.5 18h1a1.5 1.5 0 001.5-1.5v-9A1.5 1.5 0 0010.5 6h-1zM3.5 10A1.5 1.5 0 002 11.5v5A1.5 1.5 0 003.5 18h1A1.5 1.5 0 006 16.5v-5A1.5 1.5 0 004.5 10h-1z"/></svg>
                        </span>
                    </div>
                    <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">{{ $analytics['active'] }}</p>
                    <p class="mt-1 text-xs text-slate-400">ongoing in {{ $analytics['periodLabel'] }}</p>
                </div>

                {{-- Completed --}}
                <div class="card card-pad">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-slate-500">Completed</span>
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 5.153a.75.75 0 01.143 1.052l-7 9.5a.75.75 0 01-1.127.075l-3.5-3.5a.75.75 0 011.06-1.06l2.894 2.893 6.48-8.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                        </span>
                    </div>
                    <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">{{ $analytics['completedThisYear'] }}</p>
                    <p class="mt-1 text-xs text-slate-400">shipped in {{ $analytics['periodLabel'] }}</p>
                </div>

                {{-- Upcoming --}}
                <div class="card card-pad">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-slate-500">Starting soon</span>
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-sky-50 text-sky-600">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.75 2a.75.75 0 01.75.75V4h6V2.75a.75.75 0 011.5 0V4h.25A2.75 2.75 0 0117 6.75v8.5A2.75 2.75 0 0114.25 18H5.75A2.75 2.75 0 013 15.25v-8.5A2.75 2.75 0 015.75 4H6V2.75A.75.75 0 015.75 2zm-1.5 6.5v6.75c0 .69.56 1.25 1.25 1.25h8.5c.69 0 1.25-.56 1.25-1.25V8.5h-11z" clip-rule="evenodd"/></svg>
                        </span>
                    </div>
                    <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">{{ $analytics['upcoming'] }}</p>
                    <p class="mt-1 text-xs text-slate-400">start in next 30 days</p>
                </div>

                {{-- Conflicts --}}
                <div class="card card-pad">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-slate-500">Scheduling conflicts</span>
                        @php $hasConflict = $analytics['conflictCount'] > 0; @endphp
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg {{ $hasConflict ? 'bg-amber-50 text-amber-600' : 'bg-emerald-50 text-emerald-600' }}">
                            @if ($hasConflict)
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.515 2.625H3.72c-1.344 0-2.187-1.458-1.515-2.625L8.485 2.495zM10 6a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 6zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                            @else
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 5.153a.75.75 0 01.143 1.052l-7 9.5a.75.75 0 01-1.127.075l-3.5-3.5a.75.75 0 011.06-1.06l2.894 2.893 6.48-8.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                            @endif
                        </span>
                    </div>
                    <p class="mt-2 text-3xl font-semibold tracking-tight {{ $hasConflict ? 'text-amber-600' : 'text-slate-900' }}">{{ $analytics['conflictCount'] }}</p>
                    <p class="mt-1 text-xs text-slate-400">
                        {{ $hasConflict ? $analytics['teamsDoubleBooked'].' '.\Illuminate\Support\Str::plural('team', $analytics['teamsDoubleBooked']).' double-booked' : 'All teams clear' }}
                    </p>
                </div>
            </div>

            {{-- Delivery progress --}}
            <div class="card card-pad">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">Delivery progress</h3>
                        <p class="mt-0.5 text-xs text-slate-400">Task status across ongoing releases</p>
                    </div>
                    <div class="text-right">
                        <span class="text-2xl font-semibold tracking-tight text-slate-900 tabular">{{ $analytics['donePct'] }}%</span>
                        <span class="ml-1 text-xs text-slate-400">done · {{ $analytics['taskTotal'] }} {{ \Illuminate\Support\Str::plural('task', $analytics['taskTotal']) }}</span>
                    </div>
                </div>

                @if ($analytics['taskTotal'] > 0)
                    <div class="mt-4 flex h-2.5 w-full gap-[2px]">
                        @foreach ($analytics['statusCounts'] as $status => $count)
                            @if ($count > 0)
                                <div class="rounded-[3px] first:rounded-l-full last:rounded-r-full"
                                     style="width: {{ $count / $analytics['taskTotal'] * 100 }}%; background-color: {{ $statusFill[$status] }}"
                                     title="{{ $analytics['statusLabels'][$status] }}: {{ $count }}"></div>
                            @endif
                        @endforeach
                    </div>
                    <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1.5">
                        @foreach ($analytics['statusCounts'] as $status => $count)
                            <span class="inline-flex items-center gap-1.5 text-xs">
                                <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $statusFill[$status] }}"></span>
                                <span class="text-slate-600">{{ $analytics['statusLabels'][$status] }}</span>
                                <span class="font-semibold text-slate-900 tabular">{{ $count }}</span>
                            </span>
                        @endforeach
                    </div>
                @else
                    <p class="mt-4 text-sm text-slate-400">No tasks on the ongoing releases in this view yet.</p>
                @endif
            </div>

            {{-- Charts: release load + team workload --}}
            <div class="grid gap-4 lg:grid-cols-2">

                {{-- Release load by month --}}
                <div class="card card-pad">
                    <h3 class="text-sm font-semibold text-slate-900">Release load</h3>
                    <p class="mt-0.5 text-xs text-slate-400">Releases in flight each month · {{ $analytics['periodLabel'] }}</p>

                    @php $mMax = $analytics['monthlyMax']; @endphp
                    <div class="mt-5">
                        <div class="flex h-40 items-end gap-1.5">
                            @foreach ($analytics['monthly'] as $m)
                                <div class="flex h-full flex-1 flex-col items-center justify-end gap-1"
                                     title="{{ $m['label'] }}: {{ $m['count'] }} {{ \Illuminate\Support\Str::plural('release', $m['count']) }}">
                                    @if ($m['count'] > 0)
                                        <span class="text-[10px] font-medium text-slate-400 tabular">{{ $m['count'] }}</span>
                                    @endif
                                    <div class="w-full max-w-[1.6rem] rounded-t-[4px] transition-colors {{ $m['current'] ? 'bg-brand-600 ring-2 ring-brand-200' : 'bg-brand-400 hover:bg-brand-500' }}"
                                         style="height: {{ $mMax > 0 ? max((int) round($m['count'] / $mMax * 80), $m['count'] > 0 ? 8 : 0) : 0 }}%"></div>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-2 flex gap-1.5">
                            @foreach ($analytics['monthly'] as $m)
                                <div class="flex-1 text-center text-[10px] {{ $m['current'] ? 'font-semibold text-brand-600' : 'text-slate-400' }}">{{ $m['label'] }}</div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Team workload --}}
                <div class="card card-pad">
                    <h3 class="text-sm font-semibold text-slate-900">Team workload</h3>
                    <p class="mt-0.5 text-xs text-slate-400">Active releases per team</p>

                    @if (empty($analytics['teamWorkload']))
                        <p class="mt-6 text-sm text-slate-400">No active releases in this view.</p>
                    @else
                        @php $twMax = $analytics['teamWorkloadMax']; @endphp
                        <ul class="mt-5 space-y-3">
                            @foreach (array_slice($analytics['teamWorkload'], 0, 6) as $t)
                                <li class="flex items-center gap-3">
                                    <span class="h-2.5 w-2.5 flex-none rounded-full" style="background-color: {{ $t['color'] }}"></span>
                                    <span class="w-24 flex-none truncate text-sm text-slate-700" title="{{ $t['name'] }}">{{ $t['name'] }}</span>
                                    <div class="relative h-2.5 flex-1 overflow-hidden rounded-full bg-slate-100">
                                        <div class="absolute inset-y-0 left-0 rounded-full bg-brand-500"
                                             style="width: {{ $twMax > 0 ? max((int) round($t['count'] / $twMax * 100), 6) : 0 }}%"></div>
                                    </div>
                                    <span class="w-5 flex-none text-right text-sm font-semibold text-slate-900 tabular">{{ $t['count'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                        @if (count($analytics['teamWorkload']) > 6)
                            <p class="mt-3 text-xs text-slate-400">+{{ count($analytics['teamWorkload']) - 6 }} more {{ \Illuminate\Support\Str::plural('team', count($analytics['teamWorkload']) - 6) }}</p>
                        @endif
                    @endif
                </div>
            </div>

            {{-- Legend --}}
            <div class="flex flex-wrap items-center gap-4 text-xs text-slate-600">
                <span class="font-medium text-slate-500">Phases:</span>
                @foreach ($phaseLabels as $key => $label)
                    <span class="inline-flex items-center gap-1.5">
                        <span class="h-3 w-3 rounded-sm" style="background-color: {{ $phaseColors[$key] }}"></span>{{ $label }}
                    </span>
                @endforeach
                @if ($hasConflicts)
                    <span class="ml-auto inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-2.5 py-1 font-medium text-amber-800">
                        <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.515 2.625H3.72c-1.344 0-2.187-1.458-1.515-2.625L8.485 2.495zM10 6a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 6zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" /></svg>
                        Amber outline = same team double-booked
                    </span>
                @endif
            </div>

            {{-- Timeline --}}
            @if (empty($groups))
                <div class="rounded-xl bg-white p-12 text-center text-slate-500 shadow">
                    No releases match this view. @if (auth()->user()->canManageReleases())<a href="{{ route('releases.create') }}" class="text-indigo-600 hover:underline">Create one</a>.@endif
                </div>
            @else
                <div class="overflow-x-auto card">
                    <div class="min-w-[900px]">
                        {{-- Month axis --}}
                        <div class="flex border-b border-slate-200 bg-slate-50">
                            <div class="w-48 flex-none px-4 py-2 text-xs font-medium uppercase tracking-wide text-slate-500">
                                {{ $filters['group_by'] === 'project' ? 'Project' : 'Team' }}
                            </div>
                            <div class="relative h-8 flex-1">
                                @foreach ($months as $m)
                                    <div class="absolute top-0 h-8 border-l border-slate-200 px-1 text-[11px] leading-8 text-slate-400"
                                         style="left: {{ $m['offset'] }}%; width: {{ $m['width'] }}%">
                                        {{ $m['label'] }}
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Rows --}}
                        @foreach ($groups as $group)
                            <div class="flex border-b border-slate-100 last:border-b-0">
                                <div class="w-48 flex-none px-4 py-4">
                                    <a href="{{ $group['type'] === 'project' ? route('projects.show', $group['id']) : route('teams.show', $group['id']) }}"
                                       class="flex items-center gap-2 text-sm font-medium text-slate-800 hover:text-indigo-600">
                                        <span class="h-3 w-3 flex-none rounded-full" style="background-color: {{ $group['color'] }}"></span>
                                        <span class="truncate">{{ $group['label'] }}</span>
                                    </a>
                                    <p class="mt-0.5 text-xs text-slate-400">{{ count($group['bars']) }} release{{ count($group['bars']) === 1 ? '' : 's' }}</p>
                                </div>

                                <div class="relative flex-1 py-3 pr-4">
                                    {{-- faint month gridlines --}}
                                    @foreach ($months as $m)
                                        <div class="pointer-events-none absolute inset-y-0 border-l border-slate-100" style="left: {{ $m['offset'] }}%"></div>
                                    @endforeach

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
                                                $sameYear = $release->start_date->year === $release->end_date->year;
                                                $startFmt = $sameYear ? $release->start_date->format('M j') : $release->start_date->format('M j, Y');
                                                $dateRange = $startFmt.' – '.$release->end_date->format('M j, Y');
                                            @endphp
                                            <div class="relative">
                                                {{-- bar --}}
                                                <div class="relative h-8">
                                                    <a href="{{ route('releases.show', $release) }}"
                                                       class="group absolute top-0 flex h-8 items-center overflow-hidden rounded-md shadow-sm ring-1 ring-inset {{ $bar['conflict'] ? 'ring-2 ring-amber-500' : 'ring-black/5' }}"
                                                       style="left: {{ $barLeft }}%; width: {{ $barWidth }}%; background-color: #f1f5f9"
                                                       title="{{ $release->name }} · {{ $dateRange }}{{ $bar['conflict'] ? ' · OVERLAP' : '' }}">
                                                        {{-- phase segments fill the bar --}}
                                                        @foreach ($bar['phases'] as $phase)
                                                            <span class="absolute top-0 h-8"
                                                                  style="left: {{ $phase['offset'] }}%; width: {{ $phase['width'] }}%; background-color: {{ $phase['color'] }}"
                                                                  title="{{ $phase['label'] }}: {{ $phase['start']->format('M j') }}–{{ $phase['end']->format('M j') }}"></span>
                                                        @endforeach
                                                        <span class="relative z-10 truncate px-2 text-[11px] font-medium text-white drop-shadow-sm">
                                                            {{ $release->name }}
                                                        </span>
                                                        @if ($bar['conflict'])
                                                            <span class="relative z-10 ml-auto mr-1 flex-none rounded-full bg-white/90 px-1 text-[10px] font-bold text-amber-700">!</span>
                                                        @endif
                                                    </a>
                                                </div>

                                                {{-- start – end dates, aligned to the bar --}}
                                                <div class="pointer-events-none relative mt-1 h-3.5">
                                                    <span class="absolute top-0 inline-flex items-center gap-1 whitespace-nowrap text-[10px] font-medium leading-none text-slate-500 tabular"
                                                          style="{{ $anchorRight ? 'right: '.(100 - $barEnd).'%' : 'left: '.$barLeft.'%' }}">
                                                        <svg class="h-2.5 w-2.5 flex-none text-slate-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg>
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
        </div>
    </div>
</x-app-layout>
