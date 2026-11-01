<x-app-layout>
    @php
        $canManage = auth()->user()->canManageWorkspace();
        $hasFilters = $filters['search'] || $filters['status'] || $filters['sort'] !== 'name';
    @endphp

    <x-slot name="header">
        <x-page-header title="Projects" description="Products and codebases that releases are planned against.">
            @if ($canManage)
                <x-slot name="actions">
                    <a href="{{ route('projects.create') }}" class="btn-primary btn-sm">
                        <x-icon name="plus" variant="outline" class="h-4 w-4" />
                        New project
                    </a>
                </x-slot>
            @endif
        </x-page-header>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container space-y-5">

            {{-- Workspace-wide overview (independent of the filters below) --}}
            @if ($overview['projects'] > 0)
                <div class="stat-strip">
                    <x-stat label="Active projects" :value="$overview['active']"
                            :sub="$overview['projects'].' in total'" icon="folder" tone="projects" />
                    <x-stat label="Archived" :value="$overview['archived']" sub="shelved, history kept"
                            icon="lock-closed" tone="slate" />
                    <x-stat label="Ongoing releases" :value="$overview['ongoing']" sub="across all projects"
                            icon="rocket" tone="releases" />
                    <x-stat label="Completed releases" :value="$overview['completed']" sub="shipped"
                            icon="check" tone="success" />
                </div>
            @endif

            <x-filter-bar :action="route('projects.index')" :active="$hasFilters" :count="$projects->count()" noun="project">
                <x-slot name="search">
                    <label for="project-search" class="sr-only">Search projects</label>
                    <input id="project-search" type="search" name="search" value="{{ $filters['search'] }}"
                           placeholder="Search projects…" class="filter-search" autocomplete="off">
                </x-slot>

                <label for="project-status" class="filter-label">Status</label>
                <select id="project-status" name="status" class="filter-select">
                    <option value="">All projects</option>
                    <option value="active" @selected($filters['status'] === 'active')>Active</option>
                    <option value="archived" @selected($filters['status'] === 'archived')>Archived</option>
                </select>

                <label for="project-sort" class="filter-label">Sort by</label>
                <select id="project-sort" name="sort" class="filter-select">
                    @foreach ($sorts as $key => $label)
                        <option value="{{ $key }}" @selected($filters['sort'] === $key)>Sort: {{ $label }}</option>
                    @endforeach
                </select>
            </x-filter-bar>

            @if ($projects->isEmpty())
                <div class="card">
                    @if ($hasFilters)
                        <x-empty-state icon="magnifying-glass" title="No projects match these filters."
                                       description="Try a different search term or status.">
                            <a href="{{ route('projects.index') }}" class="btn-secondary btn-sm">
                                <x-icon name="x-mark" variant="outline" class="h-4 w-4" />Clear filters
                            </a>
                        </x-empty-state>
                    @else
                        <x-empty-state icon="folder" title="No projects yet."
                                       description="Add a project so releases can be planned against it.">
                            @if ($canManage)
                                <a href="{{ route('projects.create') }}" class="btn-primary btn-sm">
                                    <x-icon name="plus" variant="outline" class="h-4 w-4" />New project
                                </a>
                            @endif
                        </x-empty-state>
                    @endif
                </div>
            @else
                <ul class="grid grid-cols-1 gap-4 md:grid-cols-2 2xl:grid-cols-3" role="list">
                    @foreach ($projects as $project)
                        @include('projects.partials.card', [
                            'project' => $project,
                            'summary' => $summaries[$project->id],
                            'canManage' => $canManage,
                        ])
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-app-layout>
