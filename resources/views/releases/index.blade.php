<x-app-layout>
    @php
        $hasFilters = $filters['status'] || $filters['projectId'] || $filters['teamId'] || $filters['year'];
        $canManage = auth()->user()->canManageReleases();
    @endphp

    <x-slot name="header">
        <x-page-header title="Releases" description="Every release window across projects and teams.">
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
        <div class="app-container space-y-4">
            <x-filter-bar :action="route('releases.index')" :active="$hasFilters" :count="$releases->count()" noun="release">
                <label for="status" class="filter-label">Status</label>
                <select id="status" name="status" class="filter-select">
                    <option value="">All statuses</option>
                    <option value="active" @selected($filters['status'] === 'active')>Ongoing</option>
                    <option value="completed" @selected($filters['status'] === 'completed')>Completed</option>
                </select>

                <label for="project_id" class="filter-label">Project</label>
                <select id="project_id" name="project_id" class="filter-select max-w-[13rem]">
                    <option value="">All projects</option>
                    @foreach ($projects as $p)
                        <option value="{{ $p->id }}" @selected($filters['projectId'] === $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>

                <label for="team_id" class="filter-label">Team</label>
                <select id="team_id" name="team_id" class="filter-select max-w-[13rem]">
                    <option value="">All teams</option>
                    @foreach ($teams as $t)
                        <option value="{{ $t->id }}" @selected($filters['teamId'] === $t->id)>{{ $t->name }}</option>
                    @endforeach
                </select>

                <label for="year" class="filter-label">Year</label>
                <select id="year" name="year" class="filter-select">
                    <option value="">All years</option>
                    @foreach ($years as $y)
                        <option value="{{ $y }}" @selected($filters['year'] === $y)>{{ $y }}</option>
                    @endforeach
                </select>
            </x-filter-bar>

            <div class="card overflow-hidden">
                @if ($releases->isEmpty())
                    @if ($hasFilters)
                        <x-empty-state icon="funnel" title="No releases match these filters."
                                       description="Try another status, project, team or year.">
                            <a href="{{ route('releases.index') }}" class="btn-secondary btn-sm">Clear all filters</a>
                        </x-empty-state>
                    @else
                        <x-empty-state icon="rocket" title="No releases yet."
                                       description="Plan a release window for a project and team to start tracking it here.">
                            @if ($canManage)
                                <a href="{{ route('releases.create') }}" class="btn-primary btn-sm">
                                    <x-icon name="plus" variant="outline" class="h-4 w-4" />New release
                                </a>
                            @endif
                        </x-empty-state>
                    @endif
                @else
                    <div class="table-wrap">
                        <table class="table-base">
                            <thead>
                                <tr>
                                    <th>Release</th>
                                    <th>Project</th>
                                    <th>Team</th>
                                    <th>Quarter</th>
                                    <th>Window</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($releases as $release)
                                    <tr class="{{ $release->isComplete() ? 'bg-slate-50/40' : '' }}">
                                        <td>
                                            <a href="{{ route('releases.show', $release) }}" class="whitespace-nowrap font-medium text-slate-900 hover:text-brand-700">{{ $release->name }}</a>
                                        </td>
                                        <td>
                                            <span class="inline-flex items-center gap-2 whitespace-nowrap text-slate-600">
                                                <span class="h-2.5 w-2.5 flex-none rounded-full" style="background-color: {{ $release->project->color }}"></span>{{ $release->project->name }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="inline-flex items-center gap-2 whitespace-nowrap text-slate-600">
                                                <span class="h-2.5 w-2.5 flex-none rounded-full" style="background-color: {{ $release->team->color }}"></span>{{ $release->team->name }}
                                            </span>
                                        </td>
                                        <td class="tabular whitespace-nowrap text-slate-600">{{ $release->year }} · {{ $release->quarterLabel() }}</td>
                                        <td class="tabular whitespace-nowrap text-slate-600">{{ $release->start_date->format('M j') }} – {{ $release->end_date->format('M j, Y') }}</td>
                                        <td class="whitespace-nowrap">
                                            @if ($release->isComplete())
                                                <span class="badge badge-success">
                                                    <x-icon name="check" variant="outline" class="h-3 w-3" />
                                                    Completed
                                                </span>
                                                <span class="tabular ml-1 text-xs text-slate-400">{{ $release->completed_at->format('M j, Y') }}</span>
                                            @else
                                                <span class="badge badge-brand">Ongoing</span>
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
    </div>
</x-app-layout>
