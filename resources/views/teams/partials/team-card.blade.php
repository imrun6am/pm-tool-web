{{--
    One team in the directory (teams.index). Expects $team from
    TeamService::directory() — members loaded, plus members_count,
    releases_count, active_releases_count and open_tasks_count — and $canManage.
--}}
@php
    $archived = $team->isArchived();
    $stack = $team->members->take(5);
    $extra = max($team->members_count - $stack->count(), 0);
@endphp
<li @class(['card flex min-w-0 flex-col overflow-hidden', 'bg-slate-50/70' => $archived])>
    <div @class(['h-1 flex-none', 'opacity-40' => $archived]) style="background-color: {{ $team->color }}" aria-hidden="true"></div>

    <div class="flex flex-1 flex-col gap-4 p-5">
        {{-- Name, status and row actions --}}
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="flex min-w-0 flex-wrap items-center gap-2">
                    <x-color-chip :color="$team->color" :label="$team->name" :href="route('teams.show', $team)"
                                  class="max-w-full text-base font-semibold text-slate-900" />
                    @if ($archived)
                        <span class="badge badge-slate">Archived</span>
                    @endif
                </div>
                @if ($team->description)
                    <p class="mt-1 line-clamp-2 text-sm text-slate-500">{{ $team->description }}</p>
                @endif
            </div>

            @if ($canManage)
                <div class="-mr-2 -mt-1 flex flex-none items-center">
                    <x-action-btn icon="pencil" tone="brand" :href="route('teams.edit', $team)" title="Edit" aria-label="Edit {{ $team->name }}" />
                    @if ($archived)
                        <form method="POST" action="{{ route('teams.restore', $team) }}">@csrf
                            <x-action-btn icon="restore" tone="success" title="Restore" aria-label="Restore {{ $team->name }}" />
                        </form>
                    @else
                        <form method="POST" action="{{ route('teams.archive', $team) }}">@csrf
                            <x-action-btn icon="archive" tone="warning" title="Archive" aria-label="Archive {{ $team->name }}" />
                        </form>
                    @endif
                    @if ($team->releases_count === 0)
                        <form method="POST" action="{{ route('teams.destroy', $team) }}"
                              data-confirm="Delete this team permanently?" data-confirm-verb="Delete">
                            @csrf @method('DELETE')
                            <x-action-btn icon="trash" tone="danger" title="Delete" aria-label="Delete {{ $team->name }}" />
                        </form>
                    @endif
                </div>
            @endif
        </div>

        {{-- Lead and members --}}
        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3">
            <div class="flex min-w-0 items-center gap-2">
                @if ($team->teamLead)
                    <x-avatar :user="$team->teamLead" size="sm" />
                    <div class="min-w-0 leading-tight">
                        <p class="truncate text-sm font-medium text-slate-800">{{ $team->teamLead->name }}</p>
                        <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Team lead</p>
                    </div>
                @else
                    <span class="inline-flex h-6 w-6 flex-none items-center justify-center rounded-full border border-dashed border-slate-300 text-slate-400" aria-hidden="true">
                        <x-icon name="user" class="h-3.5 w-3.5" />
                    </span>
                    <p class="text-sm text-slate-400">No lead assigned</p>
                @endif
            </div>

            <div class="flex items-center gap-2" title="{{ $team->members->pluck('name')->implode(', ') }}">
                @if ($stack->isNotEmpty())
                    <div class="flex -space-x-1.5">
                        @foreach ($stack as $member)
                            <x-avatar :user="$member" size="sm" class="ring-2 ring-white" />
                        @endforeach
                        @if ($extra > 0)
                            <span class="inline-flex h-6 min-w-[1.5rem] items-center justify-center rounded-full bg-slate-100 px-1 text-[10px] font-semibold text-slate-600 ring-2 ring-white" aria-hidden="true">+{{ $extra }}</span>
                        @endif
                    </div>
                @endif
                <span class="tabular whitespace-nowrap text-xs text-slate-500">{{ $team->members_count }} {{ Str::plural('member', $team->members_count) }}</span>
            </div>
        </div>

        {{-- Delivery numbers --}}
        <dl class="mt-auto grid grid-cols-3 divide-x divide-slate-100 rounded-lg bg-slate-50/80 ring-1 ring-inset ring-slate-100">
            <div class="flex flex-col justify-between gap-1 px-3 py-2.5">
                <dt class="text-[11px] font-medium leading-tight text-slate-500">Active releases</dt>
                <dd class="tabular text-lg font-semibold leading-none text-slate-900">{{ $team->active_releases_count }}</dd>
            </div>
            <div class="flex flex-col justify-between gap-1 px-3 py-2.5">
                <dt class="text-[11px] font-medium leading-tight text-slate-500">Total releases</dt>
                <dd class="tabular text-lg font-semibold leading-none text-slate-900">{{ $team->releases_count }}</dd>
            </div>
            <div class="flex flex-col justify-between gap-1 px-3 py-2.5">
                <dt class="text-[11px] font-medium leading-tight text-slate-500">Open tasks</dt>
                <dd class="tabular text-lg font-semibold leading-none text-slate-900">{{ (int) $team->open_tasks_count }}</dd>
            </div>
        </dl>
    </div>

    <div class="card-footer justify-between">
        <a href="{{ route('board.index', ['team_id' => $team->id]) }}"
           class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-600 transition-colors hover:text-brand-700">
            <x-icon name="view-columns" variant="outline" class="h-4 w-4" />Board
        </a>
        <a href="{{ route('teams.show', $team) }}"
           class="inline-flex items-center gap-1 text-sm font-medium text-brand-700 transition-colors hover:text-brand-800">
            View team<span class="sr-only">: {{ $team->name }}</span>
            <x-icon name="arrow-right" variant="outline" class="h-4 w-4" />
        </a>
    </div>
</li>
