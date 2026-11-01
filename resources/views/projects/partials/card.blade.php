{{--
    One project on the index: identity, release split, counted-task progress,
    the release to watch and the teams involved.
    Expects: $project (with releases_count), $summary (ProjectService::cardSummaries row), $canManage.
--}}
@php
    $archived = $project->isArchived();
    $pct = $summary['total'] > 0 ? (int) round($summary['done'] / $summary['total'] * 100) : null;
    $next = $summary['next'];
    $teams = $summary['teams'];
    $shownTeams = $teams->take(4);
@endphp
<li @class(['card relative flex min-w-0 flex-col overflow-hidden', 'bg-slate-50/60' => $archived])>
    {{-- Project color accent --}}
    <span @class(['absolute inset-y-0 left-0 w-1', 'opacity-40' => $archived])
          style="background-color: {{ $project->color }}" aria-hidden="true"></span>

    <div class="flex flex-1 flex-col gap-4 p-5 pl-6">
        {{-- Identity --}}
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h2 class="flex min-w-0 items-center gap-2">
                    <span class="h-3 w-3 flex-none rounded-full ring-2 ring-white" style="background-color: {{ $project->color }}" aria-hidden="true"></span>
                    <a href="{{ route('projects.show', $project) }}"
                       @class(['truncate text-base font-semibold hover:text-brand-700', 'text-slate-600' => $archived, 'text-slate-900' => ! $archived])>{{ $project->name }}</a>
                </h2>
                @if ($project->description)
                    <p class="mt-1 line-clamp-2 text-sm text-slate-500">{{ $project->description }}</p>
                @else
                    <p class="mt-1 text-sm italic text-slate-400">No description.</p>
                @endif
            </div>
            @if ($archived)
                <span class="badge badge-slate flex-none">
                    <x-icon name="lock-closed" variant="outline" class="h-3 w-3" />Archived
                </span>
            @endif
        </div>

        {{-- Releases + task progress --}}
        <dl class="grid grid-cols-3 gap-3 rounded-lg border border-slate-100 bg-white px-3 py-2.5">
            <div class="min-w-0">
                <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Ongoing</dt>
                <dd class="tabular mt-0.5 text-lg font-semibold leading-none text-slate-900">{{ $summary['ongoing'] }}</dd>
            </div>
            <div class="min-w-0">
                <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Completed</dt>
                <dd class="tabular mt-0.5 text-lg font-semibold leading-none text-slate-900">{{ $summary['completed'] }}</dd>
            </div>
            <div class="min-w-0">
                <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Tasks done</dt>
                <dd class="tabular mt-0.5 text-lg font-semibold leading-none text-slate-900">{{ $pct !== null ? $pct.'%' : '—' }}</dd>
            </div>
        </dl>

        <div>
            <p class="mb-1.5 flex items-center justify-between text-xs text-slate-500">
                <span>Task progress</span>
                <span class="text-slate-400">across {{ $project->releases_count }} {{ Str::plural('release', $project->releases_count) }}</span>
            </p>
            <x-progress :done="$summary['done']" :total="$summary['total']" size="md" />
        </div>

        {{-- The release to watch --}}
        <div class="rounded-lg bg-slate-50 px-3 py-2.5 ring-1 ring-inset ring-slate-100">
            @if ($next)
                <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">
                    {{ match ($next->state()) { 'active' => 'Current release', 'upcoming' => 'Next release', default => 'Needs wrap-up' } }}
                </p>
                <div class="mt-1 flex min-w-0 flex-wrap items-center justify-between gap-x-3 gap-y-1">
                    <a href="{{ route('releases.show', $next) }}" class="min-w-0 truncate text-sm font-medium text-slate-800 hover:text-brand-700">{{ $next->name }}</a>
                    <x-release-state :release="$next" class="flex-none" />
                </div>
                <p class="tabular mt-1 flex flex-wrap items-center gap-x-1.5 gap-y-1 text-xs text-slate-500">
                    <x-icon name="calendar" variant="outline" class="h-3.5 w-3.5 flex-none text-slate-400" />
                    {{ $next->start_date->format('M j') }} – {{ $next->end_date->format('M j, Y') }}
                    @if ($next->team)
                        <span class="text-slate-300" aria-hidden="true">·</span>
                        <x-color-chip :color="$next->team->color" :label="$next->team->name" class="text-slate-600" />
                    @endif
                </p>
            @else
                <p class="flex items-center gap-2 text-sm text-slate-500">
                    <x-icon name="rocket" variant="outline" class="h-4 w-4 flex-none text-slate-400" />
                    {{ $project->releases_count > 0 ? 'Every release is completed.' : 'No releases planned yet.' }}
                </p>
            @endif
        </div>

        {{-- Teams involved --}}
        <div class="mt-auto">
            <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Teams</p>
            @if ($teams->isEmpty())
                <p class="mt-1 text-sm text-slate-400">No teams yet.</p>
            @else
                <div class="mt-1.5 flex min-w-0 flex-wrap gap-1.5">
                    @foreach ($shownTeams as $team)
                        <x-color-chip :color="$team->color" :label="$team->name"
                                      class="max-w-[10rem] rounded-full bg-slate-50 px-2 py-0.5 text-xs text-slate-700 ring-1 ring-inset ring-slate-200" />
                    @endforeach
                    @if ($teams->count() > $shownTeams->count())
                        <span class="rounded-full px-2 py-0.5 text-xs text-slate-500 ring-1 ring-inset ring-slate-200"
                              title="{{ $teams->slice($shownTeams->count())->pluck('name')->implode(', ') }}">+{{ $teams->count() - $shownTeams->count() }}</span>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- Footer: open + lead actions --}}
    <div class="flex items-center justify-between gap-2 border-t border-slate-100 bg-slate-50/60 py-2 pl-6 pr-3">
        <a href="{{ route('projects.show', $project) }}" class="inline-flex items-center gap-1 text-sm font-medium text-brand-700 hover:text-brand-800">
            Open project
            <x-icon name="arrow-right" variant="outline" class="h-4 w-4" />
        </a>
        @if ($canManage)
            <div class="flex items-center gap-0.5">
                <x-action-btn icon="pencil" tone="info" :href="route('projects.edit', $project)" title="Edit" aria-label="Edit {{ $project->name }}" />
                @if ($archived)
                    <form method="POST" action="{{ route('projects.restore', $project) }}">@csrf
                        <x-action-btn icon="restore" tone="success" title="Restore" aria-label="Restore {{ $project->name }}" />
                    </form>
                @else
                    <form method="POST" action="{{ route('projects.archive', $project) }}">@csrf
                        <x-action-btn icon="archive" tone="warning" title="Archive" aria-label="Archive {{ $project->name }}" />
                    </form>
                @endif
                @if ($project->releases_count === 0)
                    <form method="POST" action="{{ route('projects.destroy', $project) }}"
                          data-confirm="Delete this project permanently?" data-confirm-verb="Delete">
                        @csrf @method('DELETE')
                        <x-action-btn icon="trash" tone="danger" title="Delete" aria-label="Delete {{ $project->name }}" />
                    </form>
                @endif
            </div>
        @endif
    </div>
</li>
