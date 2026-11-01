<x-app-layout>
    @php
        $canManage = auth()->user()->canManageWorkspace();
        $hasFilters = $filters['search'] !== null || $filters['archived'] || $filters['sort'] !== 'name';
        $activeTeams = $teams->whereNull('archived_at');
        $byOpenTasks = $activeTeams
            ->filter(fn ($t) => $t->open_tasks_count > 0)
            ->sortByDesc('open_tasks_count')
            ->map(fn ($t) => ['label' => $t->name, 'value' => (int) $t->open_tasks_count, 'color' => $t->color])
            ->values()->all();
        $byMembers = $activeTeams
            ->filter(fn ($t) => $t->members_count > 0)
            ->sortByDesc('members_count')
            ->map(fn ($t) => ['label' => $t->name, 'value' => $t->members_count, 'color' => $t->color])
            ->values()->all();
    @endphp

    <x-slot name="header">
        <x-page-header title="Teams" description="Delivery teams, their members and the releases they own.">
            @if ($canManage)
                <x-slot name="actions">
                    <a href="{{ route('teams.create') }}" class="btn-primary btn-sm">
                        <x-icon name="plus" variant="outline" class="h-4 w-4" />
                        New team
                    </a>
                </x-slot>
            @endif
        </x-page-header>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container space-y-4">

            {{-- Workspace overview (independent of the filters) --}}
            <div class="stat-strip">
                <x-stat label="Teams" :value="$overview['teams']"
                        :sub="$overview['archived'] ? $overview['archived'].' archived' : 'all active'"
                        icon="user-group" tone="teams" />
                <x-stat label="People" :value="$overview['people']" sub="on active teams" icon="identification" tone="sky" />
                <x-stat label="Active releases" :value="$overview['activeReleases']" sub="not yet completed" icon="rocket" tone="releases" />
                <x-stat label="Open tasks" :value="$overview['openTasks']" sub="across active teams" icon="clipboard-check" tone="brand"
                        :href="route('board.index')" />
            </div>

            <x-filter-bar :action="route('teams.index')" :active="$hasFilters" :count="$teams->count()" noun="team">
                <x-slot name="search">
                    <label for="team-search" class="filter-label">Search teams</label>
                    <input id="team-search" type="search" name="search" value="{{ $filters['search'] }}"
                           placeholder="Search teams…" class="filter-search" autocomplete="off">
                </x-slot>

                <label for="sort" class="filter-label">Sort by</label>
                <select id="sort" name="sort" class="filter-select">
                    @foreach ($sorts as $value => $label)
                        <option value="{{ $value }}" @selected($filters['sort'] === $value)>Sort: {{ $label }}</option>
                    @endforeach
                </select>

                <label class="inline-flex h-9 cursor-pointer items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-600 shadow-sm transition-colors hover:border-slate-300">
                    <input type="checkbox" name="archived" value="1" class="field-check" @checked($filters['archived'])>
                    Show archived
                    @if ($overview['archived'])
                        <span class="tabular text-xs text-slate-400">{{ $overview['archived'] }}</span>
                    @endif
                </label>
            </x-filter-bar>

            @if ($teams->isEmpty())
                <div class="card">
                    @if ($filters['search'] !== null)
                        <x-empty-state icon="funnel" title="No teams match “{{ $filters['search'] }}”."
                                       :description="$filters['archived'] ? 'Try a different name.' : 'Try a different name, or include archived teams.'">
                            <a href="{{ route('teams.index') }}" class="btn-secondary btn-sm">Clear all filters</a>
                        </x-empty-state>
                    @elseif (! $filters['archived'] && $overview['archived'] > 0)
                        <x-empty-state icon="user-group" title="No active teams."
                                       description="Every team is archived. Restore one, or create a new team.">
                            <a href="{{ route('teams.index', ['archived' => 1]) }}" class="btn-secondary btn-sm">Show archived</a>
                            @if ($canManage)
                                <a href="{{ route('teams.create') }}" class="btn-primary btn-sm">
                                    <x-icon name="plus" variant="outline" class="h-4 w-4" />New team
                                </a>
                            @endif
                        </x-empty-state>
                    @else
                        <x-empty-state icon="user-group" title="No teams yet."
                                       description="Create a team, add its members and assign it releases.">
                            @if ($canManage)
                                <a href="{{ route('teams.create') }}" class="btn-primary btn-sm">
                                    <x-icon name="plus" variant="outline" class="h-4 w-4" />New team
                                </a>
                            @endif
                        </x-empty-state>
                    @endif
                </div>
            @else
                <ul role="list" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                    @foreach ($teams as $team)
                        @include('teams.partials.team-card', ['team' => $team, 'canManage' => $canManage])
                    @endforeach
                </ul>
            @endif

            {{-- Breakdowns below the list so the teams are visible first --}}
            @if ($activeTeams->isNotEmpty())
                <div class="grid gap-4 pt-2 lg:grid-cols-2">
                    @include('partials.hbar-chart', [
                        'title' => 'Open work by team',
                        'subtitle' => 'Open tasks on active teams, busiest first',
                        'rows' => $byOpenTasks,
                        'emptyText' => 'No open tasks on active teams.',
                    ])
                    @include('partials.hbar-chart', [
                        'title' => 'Members by team',
                        'subtitle' => 'Active teams, largest first',
                        'rows' => $byMembers,
                        'barClass' => 'bg-sky-500',
                        'emptyText' => 'No members on active teams yet.',
                    ])
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
