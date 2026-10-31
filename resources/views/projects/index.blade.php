<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="page-title">Projects</h2>
            @if (auth()->user()->canManageWorkspace())
                <a href="{{ route('projects.create') }}" class="btn-primary btn-sm">
                    <x-icon name="plus" class="h-4 w-4" />
                    New project
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="app-container space-y-6">

            {{-- Overview --}}
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
                @endphp

                <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    @include('partials.stat-tile', ['label' => 'Projects', 'value' => $pTotal, 'sub' => 'total', 'icon' => 'folder', 'tone' => 'brand'])
                    @include('partials.stat-tile', ['label' => 'Active', 'value' => $pActive, 'sub' => 'in progress', 'icon' => 'folder', 'tone' => 'emerald'])
                    @include('partials.stat-tile', ['label' => 'Archived', 'value' => $pArchived, 'sub' => 'shelved', 'icon' => 'archive', 'tone' => 'slate'])
                    @include('partials.stat-tile', ['label' => 'Releases', 'value' => $pReleases, 'sub' => 'across all projects', 'icon' => 'rocket', 'tone' => 'sky'])
                </div>

                @include('partials.hbar-chart', [
                    'title' => 'Releases by project',
                    'subtitle' => 'Active projects, busiest first',
                    'rows' => $pByReleases,
                    'emptyText' => 'No releases assigned to active projects yet.',
                ])
            @endif

            <div class="card overflow-hidden">
                @if ($projects->isEmpty())
                    <div class="p-12 text-center">
                        <x-icon name="folder" class="mx-auto h-10 w-10 text-slate-300" />
                        <p class="mt-3 text-sm text-slate-500">No projects yet.</p>
                    </div>
                @else
                    <table class="table-base">
                        <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-6 py-3">Project</th>
                                <th class="px-6 py-3">Releases</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($projects as $project)
                                <tr class="{{ $project->isArchived() ? 'bg-slate-50/60' : '' }}">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <span class="inline-block h-3 w-3 flex-none rounded-full" style="background-color: {{ $project->color }}"></span>
                                            <div>
                                                <a href="{{ route('projects.show', $project) }}" class="font-medium text-slate-900 hover:text-indigo-600">{{ $project->name }}</a>
                                                @if ($project->description)
                                                    <p class="text-xs text-slate-500">{{ Str::limit($project->description, 80) }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-slate-600">{{ $project->releases_count }}</td>
                                    <td class="px-6 py-4">
                                        @if ($project->isArchived())
                                            <span class="rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-medium text-slate-700">Archived</span>
                                        @else
                                            <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">Active</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-end gap-1">
                                            <x-action-btn icon="eye" tone="brand" :href="route('projects.show', $project)" title="View" aria-label="View {{ $project->name }}" />
                                            @if (auth()->user()->canManageWorkspace())
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
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
