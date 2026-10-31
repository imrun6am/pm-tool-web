<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="page-title">Teams</h2>
            @if (auth()->user()->canManageWorkspace())
                <a href="{{ route('teams.create') }}" class="btn-primary btn-sm">
                    <x-icon name="plus" class="h-4 w-4" />
                    New team
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="app-container space-y-6">

            {{-- Overview --}}
            @if ($teams->isNotEmpty())
                @php
                    $activeTeams = $teams->whereNull('archived_at');
                    $tTotal = $teams->count();
                    $tActive = $activeTeams->count();
                    $tArchived = $tTotal - $tActive;
                    $tMembers = $teams->sum('members_count');
                    $tReleases = $teams->sum('releases_count');
                    $tByReleases = $activeTeams
                        ->filter(fn ($t) => $t->releases_count > 0)
                        ->sortByDesc('releases_count')
                        ->map(fn ($t) => ['label' => $t->name, 'value' => $t->releases_count, 'color' => $t->color])
                        ->values()->all();
                    $tByMembers = $activeTeams
                        ->filter(fn ($t) => $t->members_count > 0)
                        ->sortByDesc('members_count')
                        ->map(fn ($t) => ['label' => $t->name, 'value' => $t->members_count, 'color' => $t->color])
                        ->values()->all();
                @endphp

                <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    @include('partials.stat-tile', ['label' => 'Teams', 'value' => $tTotal, 'sub' => 'total', 'icon' => 'team', 'tone' => 'brand'])
                    @include('partials.stat-tile', ['label' => 'Active', 'value' => $tActive, 'sub' => 'delivering', 'icon' => 'team', 'tone' => 'emerald'])
                    @include('partials.stat-tile', ['label' => 'Members', 'value' => $tMembers, 'sub' => 'assignments across teams', 'icon' => 'users', 'tone' => 'sky'])
                    @include('partials.stat-tile', ['label' => 'Releases', 'value' => $tReleases, 'sub' => 'across all teams', 'icon' => 'rocket', 'tone' => 'amber'])
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    @include('partials.hbar-chart', [
                        'title' => 'Releases by team',
                        'subtitle' => 'Active teams, busiest first',
                        'rows' => $tByReleases,
                        'emptyText' => 'No releases assigned to active teams yet.',
                    ])
                    @include('partials.hbar-chart', [
                        'title' => 'Members by team',
                        'subtitle' => 'Active teams, largest first',
                        'rows' => $tByMembers,
                        'barClass' => 'bg-sky-500',
                        'emptyText' => 'No members on active teams yet.',
                    ])
                </div>
            @endif

            <div class="card overflow-hidden">
                @if ($teams->isEmpty())
                    <div class="p-12 text-center">
                        <x-icon name="team" class="mx-auto h-10 w-10 text-slate-300" />
                        <p class="mt-3 text-sm text-slate-500">No teams yet.</p>
                    </div>
                @else
                    <table class="table-base">
                        <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-6 py-3">Team</th>
                                <th class="px-6 py-3">Releases</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($teams as $team)
                                <tr class="{{ $team->isArchived() ? 'bg-slate-50/60' : '' }}">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <span class="inline-block h-3 w-3 flex-none rounded-full" style="background-color: {{ $team->color }}"></span>
                                            <div>
                                                <a href="{{ route('teams.show', $team) }}" class="font-medium text-slate-900 hover:text-indigo-600">{{ $team->name }}</a>
                                                @if ($team->description)
                                                    <p class="text-xs text-slate-500">{{ Str::limit($team->description, 80) }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-slate-600">{{ $team->releases_count }}</td>
                                    <td class="px-6 py-4">
                                        @if ($team->isArchived())
                                            <span class="rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-medium text-slate-700">Archived</span>
                                        @else
                                            <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">Active</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-end gap-1">
                                            <x-action-btn icon="eye" tone="brand" :href="route('teams.show', $team)" title="View" aria-label="View {{ $team->name }}" />
                                            @if (auth()->user()->canManageWorkspace())
                                                <x-action-btn icon="pencil" tone="brand" :href="route('teams.edit', $team)" title="Edit" aria-label="Edit {{ $team->name }}" />
                                                @if ($team->isArchived())
                                                    <form method="POST" action="{{ route('teams.restore', $team) }}">@csrf
                                                        <x-action-btn icon="restore" tone="emerald" title="Restore" aria-label="Restore {{ $team->name }}" />
                                                    </form>
                                                @else
                                                    <form method="POST" action="{{ route('teams.archive', $team) }}">@csrf
                                                        <x-action-btn icon="archive" tone="amber" title="Archive" aria-label="Archive {{ $team->name }}" />
                                                    </form>
                                                @endif
                                                @if ($team->releases_count === 0)
                                                    <form method="POST" action="{{ route('teams.destroy', $team) }}"
                                                          data-confirm="Delete this team permanently?" data-confirm-verb="Delete">
                                                        @csrf @method('DELETE')
                                                        <x-action-btn icon="trash" tone="rose" title="Delete" aria-label="Delete {{ $team->name }}" />
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
