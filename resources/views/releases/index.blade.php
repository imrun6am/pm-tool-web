<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="page-title">Releases</h2>
            @if (auth()->user()->canManageReleases())
                <a href="{{ route('releases.create') }}" class="btn-primary btn-sm">
                    <x-icon name="plus" class="h-4 w-4" />
                    New release
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container space-y-6">
            {{-- Filters --}}
            <form method="GET" action="{{ route('releases.index') }}" class="card p-4">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <div>
                        <label class="eyebrow">Status</label>
                        <select name="status" class="field-select">
                            <option value="">All</option>
                            <option value="active" @selected($filters['status'] === 'active')>Ongoing</option>
                            <option value="completed" @selected($filters['status'] === 'completed')>Completed</option>
                        </select>
                    </div>
                    <div>
                        <label class="eyebrow">Project</label>
                        <select name="project_id" class="field-select">
                            <option value="">All projects</option>
                            @foreach ($projects as $p)
                                <option value="{{ $p->id }}" @selected($filters['projectId'] === $p->id)>{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="eyebrow">Team</label>
                        <select name="team_id" class="field-select">
                            <option value="">All teams</option>
                            @foreach ($teams as $t)
                                <option value="{{ $t->id }}" @selected($filters['teamId'] === $t->id)>{{ $t->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="eyebrow">Year</label>
                        <select name="year" class="field-select">
                            <option value="">All years</option>
                            @foreach ($years as $y)
                                <option value="{{ $y }}" @selected($filters['year'] === $y)>{{ $y }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button class="btn-primary btn-sm">Apply</button>
                        <a href="{{ route('releases.index') }}" class="btn-secondary btn-sm">Reset</a>
                    </div>
                </div>
            </form>

            <div class="card overflow-hidden">
                @if ($releases->isEmpty())
                    <div class="p-12 text-center text-sm text-slate-500">No releases match this view.</div>
                @else
                    <div class="overflow-x-auto">
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
                                            <a href="{{ route('releases.show', $release) }}" class="font-medium text-slate-900 hover:text-brand-600">{{ $release->name }}</a>
                                        </td>
                                        <td>
                                            <span class="inline-flex items-center gap-2 text-slate-600">
                                                <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $release->project->color }}"></span>{{ $release->project->name }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="inline-flex items-center gap-2 text-slate-600">
                                                <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $release->team->color }}"></span>{{ $release->team->name }}
                                            </span>
                                        </td>
                                        <td class="text-slate-600">{{ $release->year }} · {{ $release->quarterLabel() }}</td>
                                        <td class="text-slate-600">{{ $release->start_date->format('M j') }} – {{ $release->end_date->format('M j, Y') }}</td>
                                        <td>
                                            @if ($release->isComplete())
                                                <span class="badge bg-emerald-50 text-emerald-700">
                                                    <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0l-3.5-3.5a1 1 0 111.4-1.4l2.8 2.79 6.8-6.79a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
                                                    Completed
                                                </span>
                                                <span class="ml-1 text-xs text-slate-400">{{ $release->completed_at->format('M j, Y') }}</span>
                                            @else
                                                <span class="badge bg-brand-50 text-brand-700">Ongoing</span>
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
