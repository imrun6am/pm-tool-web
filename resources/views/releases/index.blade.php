<x-app-layout>
    @php
        $hasFilters = $filters['status'] || $filters['projectId'] || $filters['teamId'] || $filters['year']
            || $filters['quarter'] || filled($filters['search']);
        $canManage = auth()->user()->canManageReleases();

        // Quick-filter strip: each Release::state() with its count. Release::state()
        // calls the in-flight state "active", which the status filter spells
        // "in_progress" ("active" there means every ongoing release).
        $stateCells = [
            ['state' => 'active', 'status' => 'in_progress', 'label' => 'In progress', 'icon' => 'rocket', 'tone' => 'brand', 'sub' => 'inside their window'],
            ['state' => 'upcoming', 'status' => 'upcoming', 'label' => 'Upcoming', 'icon' => 'calendar', 'tone' => 'sky', 'sub' => 'not started yet'],
            ['state' => 'overdue', 'status' => 'overdue', 'label' => 'Past window', 'icon' => 'clock', 'tone' => 'warning', 'sub' => 'ended, not completed'],
            ['state' => 'completed', 'status' => 'completed', 'label' => 'Completed', 'icon' => 'check', 'tone' => 'success', 'sub' => 'shipped'],
        ];
        $sorts = [
            '' => 'Newest quarter',
            'start' => 'Start date ↑',
            '-start' => 'Start date ↓',
            'end' => 'End date ↑',
            '-end' => 'End date ↓',
            'name' => 'Name A–Z',
            '-name' => 'Name Z–A',
            '-progress' => 'Most progress',
            'progress' => 'Least progress',
        ];
        $query = request()->except(['page', 'status']);
    @endphp

    <x-slot name="header">
        <x-page-header title="Releases" description="Every release window across projects and teams — where it stands, who's on it, and how far along it is.">
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
        <div class="app-container space-y-5">
            {{-- Quick filters by state; counts follow every filter but status. Click again to clear. --}}
            <nav class="stat-strip" aria-label="Filter by state">
                @foreach ($stateCells as $cell)
                    @php $on = $filters['status'] === $cell['status']; @endphp
                    <x-stat :label="$cell['label']" :value="number_format($stateCounts[$cell['state']] ?? 0)"
                            :sub="$on ? 'Showing only these · clear' : $cell['sub']" :icon="$cell['icon']" :tone="$cell['tone']"
                            :href="route('releases.index', $on ? $query : $query + ['status' => $cell['status']])"
                            :aria-current="$on ? 'true' : null"
                            @class(['!bg-brand-50/60 ring-2 ring-inset ring-brand-500/30' => $on]) />
                @endforeach
            </nav>

            <x-filter-bar :action="route('releases.index')" :active="$hasFilters" :count="$releases->total()" noun="release">
                <x-slot name="search">
                    <label for="release-search" class="sr-only">Search releases</label>
                    <input id="release-search" type="search" name="search" value="{{ $filters['search'] }}"
                           placeholder="Search releases…" maxlength="100" autocomplete="off" class="filter-search w-full">
                </x-slot>

                <label for="status" class="filter-label">Status</label>
                <select id="status" name="status" class="filter-select">
                    <option value="">All statuses</option>
                    <option value="active" @selected($filters['status'] === 'active')>Ongoing</option>
                    <option value="in_progress" @selected($filters['status'] === 'in_progress')>In progress</option>
                    <option value="upcoming" @selected($filters['status'] === 'upcoming')>Upcoming</option>
                    <option value="overdue" @selected($filters['status'] === 'overdue')>Past window</option>
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

                <label for="quarter" class="filter-label">Quarter</label>
                <select id="quarter" name="quarter" class="filter-select">
                    <option value="">All quarters</option>
                    @foreach ([1, 2, 3, 4] as $q)
                        <option value="{{ $q }}" @selected($filters['quarter'] === $q)>Q{{ $q }}</option>
                    @endforeach
                </select>

                <label for="sort" class="filter-label">Sort by</label>
                <select id="sort" name="sort" class="filter-select" title="Sort by">
                    @foreach ($sorts as $value => $label)
                        <option value="{{ $value }}" @selected((string) $filters['sort'] === (string) $value)>{{ $value === '' ? 'Sort: '.$label : $label }}</option>
                    @endforeach
                </select>
            </x-filter-bar>

            <div class="card overflow-hidden">
                @if ($releases->isEmpty())
                    @if ($hasFilters)
                        <x-empty-state icon="funnel" title="No releases match these filters."
                                       description="Try a different search, status, project, team or period.">
                            <a href="{{ route('releases.index') }}" class="btn-secondary btn-sm">Clear all filters</a>
                        </x-empty-state>
                    @else
                        <x-empty-state icon="rocket" title="No releases yet."
                                       description="Plan a release window for a project and team, split it into phases, and track it here.">
                            @if ($canManage)
                                <a href="{{ route('releases.create') }}" class="btn-primary btn-sm">
                                    <x-icon name="plus" variant="outline" class="h-4 w-4" />New release
                                </a>
                            @endif
                        </x-empty-state>
                    @endif
                @else
                    @include('partials.releases-table', ['releases' => $releases, 'conflicts' => $conflicts])

                    @if ($releases->hasPages())
                        <div class="border-t border-slate-100 px-4 py-3 sm:px-6">{{ $releases->links() }}</div>
                    @endif
                @endif
            </div>

            @if (collect($conflicts)->filter()->isNotEmpty())
                <p class="flex items-start gap-2 text-xs text-slate-500">
                    <x-icon name="exclamation-circle" variant="outline" class="mt-px h-4 w-4 flex-none text-warning-500" />
                    <span><span class="font-medium text-slate-700">Overlap</span> marks a release whose team is booked by another ongoing release in the same window. It's a warning, not a block.</span>
                </p>
            @endif
        </div>
    </div>
</x-app-layout>
