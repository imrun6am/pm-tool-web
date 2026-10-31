<x-app-layout>
    @php $canManage = auth()->user()->canManageWorkspace(); @endphp

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

            {{-- Compact overview --}}
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
                    $summary = [
                        ['label' => 'Teams', 'value' => $tTotal, 'sub' => 'total', 'icon' => 'team', 'tone' => 'bg-brand-50 text-brand-600'],
                        ['label' => 'Active', 'value' => $tActive, 'sub' => 'delivering', 'icon' => 'team', 'tone' => 'bg-emerald-50 text-emerald-600'],
                        ['label' => 'Members', 'value' => $tMembers, 'sub' => 'assignments across teams', 'icon' => 'users', 'tone' => 'bg-sky-50 text-sky-600'],
                        ['label' => 'Releases', 'value' => $tReleases, 'sub' => 'across all teams', 'icon' => 'rocket', 'tone' => 'bg-amber-50 text-amber-600'],
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
                @if ($teams->isEmpty())
                    <x-empty-state icon="user-group" title="No teams yet."
                                   description="Create a team, add its members and assign it releases.">
                        @if ($canManage)
                            <a href="{{ route('teams.create') }}" class="btn-primary btn-sm">
                                <x-icon name="plus" variant="outline" class="h-4 w-4" />New team
                            </a>
                        @endif
                    </x-empty-state>
                @else
                    <div class="table-wrap">
                    <table class="table-base">
                        <thead>
                            <tr>
                                <th>Team</th>
                                <th>Releases</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($teams as $team)
                                <tr class="{{ $team->isArchived() ? 'bg-slate-50/60' : '' }}">
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <span class="inline-block h-3 w-3 flex-none rounded-full" style="background-color: {{ $team->color }}"></span>
                                            <div>
                                                <a href="{{ route('teams.show', $team) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $team->name }}</a>
                                                @if ($team->description)
                                                    <p class="text-xs text-slate-500">{{ Str::limit($team->description, 80) }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="tabular text-slate-600">{{ $team->releases_count }}</td>
                                    <td>
                                        @if ($team->isArchived())
                                            <span class="badge badge-slate">Archived</span>
                                        @else
                                            <span class="badge badge-success">Active</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="flex items-center justify-end gap-1">
                                            <x-action-btn icon="eye" tone="brand" :href="route('teams.show', $team)" title="View" aria-label="View {{ $team->name }}" />
                                            @if ($canManage)
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
                    </div>
                @endif
            </div>

            {{-- Breakdowns below the table so the list is visible first --}}
            @if ($teams->isNotEmpty())
                <div class="grid gap-4 pt-2 lg:grid-cols-2">
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
        </div>
    </div>
</x-app-layout>
