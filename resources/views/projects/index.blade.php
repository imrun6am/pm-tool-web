<x-app-layout>
    @php $canManage = auth()->user()->canManageWorkspace(); @endphp

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
        <div class="app-container space-y-4">

            {{-- Compact overview --}}
            @if ($projects->isNotEmpty())
                @php
                    $activeProjects = $projects->whereNull('archived_at');
                    $pTotal = $projects->count();
                    $pActive = $activeProjects->count();
                    $pArchived = $pTotal - $pActive;
                    $pReleases = $projects->sum('releases_count');
                    $pByReleases = $activeProjects
                        ->filter(fn ($p) => $p->releases_count > 0)
                        ->sortByDesc('releases_count')
                        ->map(fn ($p) => ['label' => $p->name, 'value' => $p->releases_count, 'color' => $p->color])
                        ->values()->all();
                    $summary = [
                        ['label' => 'Projects', 'value' => $pTotal, 'sub' => 'total', 'icon' => 'folder', 'tone' => 'bg-brand-50 text-brand-600'],
                        ['label' => 'Active', 'value' => $pActive, 'sub' => 'in progress', 'icon' => 'folder', 'tone' => 'bg-emerald-50 text-emerald-600'],
                        ['label' => 'Archived', 'value' => $pArchived, 'sub' => 'shelved', 'icon' => 'archive', 'tone' => 'bg-slate-100 text-slate-600'],
                        ['label' => 'Releases', 'value' => $pReleases, 'sub' => 'across all projects', 'icon' => 'rocket', 'tone' => 'bg-sky-50 text-sky-600'],
                    ];
                @endphp

                <dl class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    @foreach ($summary as $s)
                        <div class="card flex items-center gap-3 px-4 py-3">
                            <span class="flex h-8 w-8 flex-none items-center justify-center rounded-lg {{ $s['tone'] }}">
                                <x-icon :name="$s['icon']" class="h-4 w-4" />
                            </span>
                            <div class="min-w-0">
                                <dt class="truncate text-xs font-medium text-slate-500" title="{{ $s['sub'] }}">{{ $s['label'] }}</dt>
                                <dd class="tabular text-lg font-semibold leading-tight text-slate-900">{{ $s['value'] }}</dd>
                            </div>
                        </div>
                    @endforeach
                </dl>
            @endif

            <div class="card overflow-hidden">
                @if ($projects->isEmpty())
                    <x-empty-state icon="folder" title="No projects yet."
                                   description="Add a project so releases can be planned against it.">
                        @if ($canManage)
                            <a href="{{ route('projects.create') }}" class="btn-primary btn-sm">
                                <x-icon name="plus" variant="outline" class="h-4 w-4" />New project
                            </a>
                        @endif
                    </x-empty-state>
                @else
                    <div class="table-wrap">
                    <table class="table-base">
                        <thead>
                            <tr>
                                <th>Project</th>
                                <th>Releases</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($projects as $project)
                                <tr class="{{ $project->isArchived() ? 'bg-slate-50/60' : '' }}">
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <span class="inline-block h-3 w-3 flex-none rounded-full" style="background-color: {{ $project->color }}"></span>
                                            <div>
                                                <a href="{{ route('projects.show', $project) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $project->name }}</a>
                                                @if ($project->description)
                                                    <p class="text-xs text-slate-500">{{ Str::limit($project->description, 80) }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="tabular text-slate-600">{{ $project->releases_count }}</td>
                                    <td>
                                        @if ($project->isArchived())
                                            <span class="badge badge-slate">Archived</span>
                                        @else
                                            <span class="badge badge-success">Active</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="flex items-center justify-end gap-1">
                                            <x-action-btn icon="eye" tone="brand" :href="route('projects.show', $project)" title="View" aria-label="View {{ $project->name }}" />
                                            @if ($canManage)
                                                <x-action-btn icon="pencil" tone="brand" :href="route('projects.edit', $project)" title="Edit" aria-label="Edit {{ $project->name }}" />
                                                @if ($project->isArchived())
                                                    <form method="POST" action="{{ route('projects.restore', $project) }}">@csrf
                                                        <x-action-btn icon="restore" tone="emerald" title="Restore" aria-label="Restore {{ $project->name }}" />
                                                    </form>
                                                @else
                                                    <form method="POST" action="{{ route('projects.archive', $project) }}">@csrf
                                                        <x-action-btn icon="archive" tone="amber" title="Archive" aria-label="Archive {{ $project->name }}" />
                                                    </form>
                                                @endif
                                                @if ($project->releases_count === 0)
                                                    <form method="POST" action="{{ route('projects.destroy', $project) }}"
                                                          data-confirm="Delete this project permanently?" data-confirm-verb="Delete">
                                                        @csrf @method('DELETE')
                                                        <x-action-btn icon="trash" tone="rose" title="Delete" aria-label="Delete {{ $project->name }}" />
                                                    </form>
                                                @endif
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                @endif
            </div>

            {{-- Breakdown below the table so the list is visible first --}}
            @if ($projects->isNotEmpty())
                <div class="pt-2">
                    @include('partials.hbar-chart', [
                        'title' => 'Releases by project',
                        'subtitle' => 'Active projects, busiest first',
                        'rows' => $pByReleases,
                        'emptyText' => 'No releases assigned to active projects yet.',
                    ])
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
