@php
    $me = auth()->user();
    $canManage = $me->canManageTeamMembers();
    $canManageTeam = $me->canManageWorkspace();
    $archived = $team->isArchived();
    $conflictCount = collect($conflicts)->filter()->count();
    $inFlight = $releases->reject(fn ($r) => $r->isComplete())->values();
    $busyThrough = $releases->max('end_date');
    $maxLoad = max((int) $tasks['byAssignee']->max(), 1);
    $boardUrl = route('board.index', ['team_id' => $team->id]);
    $tasksheetUrl = route('tasksheet.index', ['team' => $team->id]);
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$team->name"
                       :breadcrumbs="['Teams' => route('teams.index'), $team->name => null]">
            <x-slot name="actions">
                <a href="{{ $boardUrl }}" class="btn-secondary btn-sm">
                    <x-icon name="view-columns" variant="outline" class="h-4 w-4" />Board
                </a>
                <a href="{{ $tasksheetUrl }}" class="btn-secondary btn-sm">
                    <x-icon name="clipboard-check" variant="outline" class="h-4 w-4" />Tasksheet
                </a>
                @if ($canManageTeam)
                    <a href="{{ route('teams.edit', $team) }}" class="btn-secondary btn-sm">
                        <x-icon name="pencil-square" variant="outline" class="h-4 w-4" />
                        Edit team
                    </a>
                    @if ($archived)
                        <form method="POST" action="{{ route('teams.restore', $team) }}">
                            @csrf
                            <button class="btn-ghost btn-sm"><x-icon name="restore" class="h-4 w-4" />Restore</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('teams.archive', $team) }}">
                            @csrf
                            <button class="btn-ghost btn-sm"><x-icon name="archive" class="h-4 w-4" />Archive</button>
                        </form>
                    @endif
                    @if ($releases->isEmpty())
                        <form method="POST" action="{{ route('teams.destroy', $team) }}"
                              data-confirm="Delete this team permanently?" data-confirm-verb="Delete">
                            @csrf @method('DELETE')
                            <button class="btn-danger btn-sm"><x-icon name="trash" variant="outline" class="h-4 w-4" />Delete</button>
                        </form>
                    @endif
                @endif
            </x-slot>
        </x-page-header>

        {{-- At a glance --}}
        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
            <span class="inline-flex items-center gap-2">
                <span class="h-3.5 w-3.5 flex-none rounded-full ring-2 ring-white ring-offset-1 ring-offset-slate-200"
                      style="background-color: {{ $team->color }}" title="Team color {{ $team->color }}" aria-hidden="true"></span>
                @if ($archived)
                    <span class="badge badge-slate">Archived</span>
                @else
                    <span class="badge badge-success">Active</span>
                @endif
            </span>
            <span class="inline-flex min-w-0 items-center gap-1.5 text-slate-500">
                @if ($team->teamLead)
                    <x-avatar :user="$team->teamLead" size="sm" />
                    <span class="truncate"><span class="text-slate-700">{{ $team->teamLead->name }}</span> · lead</span>
                @else
                    <x-icon name="user" class="h-4 w-4 text-slate-400" />No lead assigned
                @endif
            </span>
            <span class="inline-flex items-center gap-1.5 text-slate-500">
                <x-icon name="user-group" variant="outline" class="h-4 w-4 text-slate-400" />
                <span class="tabular">{{ $team->members->count() }}</span> {{ Str::plural('member', $team->members->count()) }}
            </span>
            @if ($busyThrough)
                <span class="inline-flex items-center gap-1.5 text-slate-500">
                    <x-icon name="calendar" variant="outline" class="h-4 w-4 text-slate-400" />
                    Booked through {{ $busyThrough->format('M j, Y') }}
                </span>
            @endif
            @if ($conflictCount)
                <a href="#releases" class="badge badge-warning">
                    <x-icon name="exclamation-circle" variant="outline" class="h-3.5 w-3.5" />
                    {{ $conflictCount }} double-booked {{ Str::plural('release', $conflictCount) }}
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container space-y-6">

            {{-- ============ KPI band ============ --}}
            <div class="stat-strip lg:grid-cols-5 max-lg:[&>*:nth-child(-n+4)]:border-b max-lg:[&>*:last-child]:col-span-2 max-lg:[&>*:last-child]:!border-r-0">
                <x-stat label="Members" :value="$team->members->count()"
                        :sub="$formerMembers->isNotEmpty() ? $formerMembers->count().' former' : 'on this team'"
                        icon="user-group" tone="sky" href="#members" />
                <x-stat label="Active releases" :value="$inFlight->count()" :sub="'of '.$releases->count().' total'"
                        icon="rocket" tone="releases" href="#releases" />
                <x-stat label="Open tasks" :value="$tasks['open']"
                        :sub="$tasks['unassigned'] ? $tasks['unassigned'].' unassigned' : 'all assigned'"
                        icon="clipboard-check" tone="brand" :href="$boardUrl" />
                <x-stat label="Overdue" :value="$tasks['overdue']" sub="past their due date"
                        :icon="$tasks['overdue'] ? 'exclamation-circle' : 'clock'" :tone="$tasks['overdue'] ? 'warning' : 'slate'"
                        :alert="$tasks['overdue'] > 0" />
                <x-stat label="Done this month" :value="$tasks['doneThisMonth']" :sub="'completed in '.now()->format('F')"
                        icon="check" tone="success" />
            </div>

            <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem] xl:grid-cols-[minmax(0,1fr)_24rem]">

                {{-- ---------- Main column ---------- --}}
                <div class="min-w-0 space-y-6">

                    {{-- Release schedule --}}
                    <section id="releases" class="card scroll-mt-24 overflow-hidden">
                        <div class="card-header">
                            <div class="min-w-0">
                                <h2 class="card-title">Release schedule</h2>
                                <p class="card-subtitle">
                                    {{ $releases->count() }} {{ Str::plural('release', $releases->count()) }}@if ($busyThrough) · booked through {{ $busyThrough->format('M j, Y') }}@endif
                                </p>
                            </div>
                            <div class="flex flex-none items-center gap-2">
                                @if ($conflictCount)
                                    <span class="badge badge-warning hidden sm:inline-flex">{{ $conflictCount }} overlapping</span>
                                @endif
                                @if ($releases->isNotEmpty())
                                    <a href="{{ route('releases.index', ['team_id' => $team->id]) }}" class="text-sm font-medium text-brand-700 hover:text-brand-800">All releases</a>
                                @endif
                            </div>
                        </div>

                        <div class="px-5 py-4 sm:px-6">
                            <x-release-timeline :releases="$releases" :conflicts="$conflicts" label="team" />
                        </div>

                        @if ($releases->isNotEmpty())
                            <div class="border-t border-slate-100">
                                <h3 class="px-5 pb-1 pt-4 text-xs font-semibold uppercase tracking-wide text-slate-400 sm:px-6">In flight</h3>
                                @if ($inFlight->isEmpty())
                                    <p class="px-5 pb-5 pt-1 text-sm text-slate-500 sm:px-6">Every release is completed — this team is free to book.</p>
                                @else
                                    <ul class="divide-y divide-slate-100">
                                        @foreach ($inFlight as $release)
                                            @php $progress = $release->taskProgress(); @endphp
                                            <li class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-3 sm:px-6">
                                                <div class="min-w-0 flex-1 basis-56">
                                                    <div class="flex min-w-0 items-center gap-2">
                                                        <a href="{{ route('releases.show', $release) }}" class="truncate text-sm font-medium text-slate-900 hover:text-brand-700">{{ $release->name }}</a>
                                                        @if ($conflicts[$release->id] ?? false)
                                                            <span class="badge badge-warning" title="Overlaps another release of this team">Overlap</span>
                                                        @endif
                                                    </div>
                                                    <div class="mt-0.5 flex min-w-0 flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-slate-500">
                                                        @if ($release->project)
                                                            <x-color-chip :color="$release->project->color" :label="$release->project->name" class="max-w-[12rem]" />
                                                        @endif
                                                        <span class="tabular whitespace-nowrap">{{ $release->start_date->format('M j') }} – {{ $release->end_date->format('M j, Y') }}</span>
                                                    </div>
                                                </div>
                                                <x-release-state :release="$release" />
                                                <x-progress :done="$progress['done']" :total="$progress['total']" class="w-full sm:w-40" />
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        @endif
                    </section>

                    {{-- Members & workload --}}
                    <section id="members" class="card scroll-mt-24 overflow-hidden">
                        <div class="card-header">
                            <div class="min-w-0">
                                <h2 class="card-title">Members <span class="font-normal text-slate-400">({{ $team->members->count() }})</span></h2>
                                <p class="card-subtitle">Workload is each person’s open tasks in this team.</p>
                            </div>
                        </div>

                        @if ($canManage)
                            <form method="POST" action="{{ route('teams.members.store', $team) }}"
                                  class="flex flex-wrap items-end gap-3 border-b border-slate-100 bg-slate-50/50 px-5 py-4 sm:px-6">
                                @csrf
                                <div class="min-w-0 flex-1 basis-56">
                                    <label for="user_id" class="eyebrow">Add a member</label>
                                    <select id="user_id" name="user_id" required class="field-input"
                                            @error('user_id') aria-invalid="true" aria-describedby="user_id-error" @enderror>
                                        <option value="">— Select a person —</option>
                                        @foreach ($assignableUsers as $user)
                                            <option value="{{ $user->id }}">{{ $user->name }} · {{ $user->roleLabel() }}</option>
                                        @endforeach
                                    </select>
                                    @error('user_id') <p id="user_id-error" class="field-error">{{ $message }}</p> @enderror
                                </div>
                                <button class="btn-primary btn-sm" @disabled($assignableUsers->isEmpty())>
                                    <x-icon name="user-plus" variant="outline" class="h-4 w-4" />Add member
                                </button>
                            </form>
                        @endif

                        @if ($team->members->isEmpty())
                            <x-empty-state icon="user-group" title="No members yet."
                                           :description="$canManage ? 'Add people above to staff this team.' : null" class="!py-10" />
                        @else
                            <ul class="divide-y divide-slate-100">
                                @foreach ($team->members as $member)
                                    @php $load = (int) ($tasks['byAssignee'][$member->id] ?? 0); @endphp
                                    <li class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-3 sm:px-6">
                                        <div class="flex min-w-0 flex-1 basis-56 items-center gap-3">
                                            <x-avatar :user="$member" />
                                            <div class="min-w-0">
                                                <p class="flex min-w-0 items-center gap-2 text-sm font-medium text-slate-800">
                                                    <span class="truncate">{{ $member->name }}</span>
                                                    @if ($member->id === $team->team_lead_id)
                                                        <span class="badge badge-brand">Lead</span>
                                                    @endif
                                                </p>
                                                <p class="truncate text-xs text-slate-400">{{ $member->email }}</p>
                                            </div>
                                        </div>
                                        <span class="flex-none">@include('partials.role-badge', ['role' => $member->role])</span>
                                        <a href="{{ route('board.index', ['team_id' => $team->id, 'assignee_id' => $member->id]) }}"
                                           class="group order-last flex w-full items-center gap-2 sm:order-none sm:w-36"
                                           title="{{ $load }} open {{ Str::plural('task', $load) }} — open on the board">
                                            <span class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100">
                                                <span @class(['block h-full rounded-full', 'bg-brand-500' => $load > 0])
                                                      style="width: {{ $load ? max((int) round($load / $maxLoad * 100), 6) : 0 }}%"></span>
                                            </span>
                                            <span class="tabular w-14 flex-none text-right text-xs text-slate-500 group-hover:text-brand-700">
                                                {{ $load }} open
                                            </span>
                                        </a>
                                        @if ($canManage)
                                            <form method="POST" action="{{ route('teams.members.destroy', [$team, $member]) }}" class="sm:order-last"
                                                  data-confirm="Remove {{ $member->name }} from this team?" data-confirm-verb="Remove">
                                                @csrf @method('DELETE')
                                                <button class="rounded-md px-1.5 py-1 text-xs text-slate-400 transition-colors hover:bg-danger-50 hover:text-danger-600">Remove<span class="sr-only"> {{ $member->name }}</span></button>
                                            </form>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @if ($tasks['unassigned'] > 0)
                            <div class="flex items-center justify-between gap-3 border-t border-slate-100 bg-warning-50/40 px-5 py-3 text-sm sm:px-6">
                                <span class="text-slate-600"><span class="tabular font-semibold text-slate-900">{{ $tasks['unassigned'] }}</span> open {{ Str::plural('task', $tasks['unassigned']) }} not assigned to anyone</span>
                                <a href="{{ $boardUrl }}" class="flex-none text-sm font-medium text-brand-700 hover:text-brand-800">Open board</a>
                            </div>
                        @endif

                        @if ($formerMembers->isNotEmpty())
                            <details class="group border-t border-slate-100">
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-3 text-sm font-medium text-slate-600 hover:bg-slate-50 sm:px-6 [&::-webkit-details-marker]:hidden">
                                    <span>Past members <span class="font-normal text-slate-400">({{ $formerMembers->count() }})</span></span>
                                    <x-icon name="chevron-down" class="h-4 w-4 text-slate-400 transition-transform group-open:rotate-180" />
                                </summary>
                                <ul class="divide-y divide-slate-100 border-t border-slate-100">
                                    @foreach ($formerMembers as $former)
                                        <li class="flex items-center gap-3 px-5 py-2.5 sm:px-6">
                                            <x-avatar :user="$former" size="sm" class="opacity-70" />
                                            <span class="min-w-0 flex-1 truncate text-sm text-slate-600">{{ $former->name }}</span>
                                            <span class="flex-none text-xs text-slate-400">Left {{ \Illuminate\Support\Carbon::parse($former->pivot->left_at)->format('M j, Y') }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                    </section>

                    {{-- Recent activity (a planning surface: never for developers and QA) --}}
                    @unless ($me->hasLimitedAccess())
                        <section class="card overflow-hidden">
                            <div class="card-header">
                                <div class="min-w-0">
                                    <h2 class="card-title">Recent activity</h2>
                                    <p class="card-subtitle">Changes to this team, its releases and its tasks.</p>
                                </div>
                                <a href="{{ route('activity.index', ['team_id' => $team->id]) }}" class="flex-none text-sm font-medium text-brand-700 hover:text-brand-800">View all</a>
                            </div>
                            <div class="divide-y divide-slate-100 px-4 sm:px-5">
                                @include('partials.activity-list', ['activities' => $activities])
                            </div>
                        </section>
                    @endunless
                </div>

                {{-- ---------- Sidebar ---------- --}}
                <div class="min-w-0 space-y-6">

                    @if ($health)
                        @include('teams.partials.health', ['health' => $health, 'tasksheetUrl' => $tasksheetUrl])
                    @endif

                    {{-- Team lead --}}
                    <section class="card overflow-hidden">
                        <div class="card-header">
                            <h2 class="card-title">Team lead</h2>
                        </div>
                        <div class="px-5 py-5 sm:px-6">
                            @if ($team->teamLead)
                                <div class="flex items-center gap-3">
                                    <x-avatar :user="$team->teamLead" size="lg" />
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-900">{{ $team->teamLead->name }}</p>
                                        <p class="truncate text-xs text-slate-400">{{ $team->teamLead->email }}</p>
                                        <span class="mt-1 inline-block">@include('partials.role-badge', ['role' => $team->teamLead->role, 'variant' => 'text'])</span>
                                    </div>
                                </div>
                            @else
                                <div class="flex items-center gap-3 text-slate-400">
                                    <span class="flex h-10 w-10 flex-none items-center justify-center rounded-full border border-dashed border-slate-300">
                                        <x-icon name="user" class="h-5 w-5" />
                                    </span>
                                    <p class="text-sm">No lead assigned yet.</p>
                                </div>
                            @endif

                            @if ($canManageTeam)
                                <form method="POST" action="{{ route('teams.lead.update', $team) }}" class="mt-4 space-y-2 border-t border-slate-100 pt-4">
                                    @csrf @method('PUT')
                                    <label for="team_lead_id" class="eyebrow">Assign lead</label>
                                    <select id="team_lead_id" name="team_lead_id" class="field-input"
                                            @error('team_lead_id') aria-invalid="true" aria-describedby="team_lead_id-error" @enderror>
                                        <option value="">— No lead —</option>
                                        @foreach ($leadCandidates as $candidate)
                                            <option value="{{ $candidate->id }}" @selected($candidate->id === $team->team_lead_id)>
                                                {{ $candidate->name }} · {{ $candidate->roleLabel() }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('team_lead_id') <p id="team_lead_id-error" class="field-error">{{ $message }}</p> @enderror
                                    <p class="text-xs text-slate-400">Any user is eligible — role doesn’t matter.</p>
                                    <button class="btn-primary btn-sm w-full">Save lead</button>
                                </form>
                            @endif
                        </div>
                    </section>

                    {{-- About --}}
                    <section class="card overflow-hidden">
                        <div class="card-header">
                            <h2 class="card-title">About</h2>
                        </div>
                        <dl class="divide-y divide-slate-100 text-sm">
                            <div class="flex items-center justify-between gap-3 px-5 py-3 sm:px-6">
                                <dt class="text-slate-500">Color</dt>
                                <dd class="flex items-center gap-2 text-slate-700">
                                    <span class="inline-block h-3.5 w-3.5 rounded-full" style="background-color: {{ $team->color }}"></span>
                                    <span class="font-mono text-xs uppercase">{{ $team->color }}</span>
                                </dd>
                            </div>
                            <div class="flex items-center justify-between gap-3 px-5 py-3 sm:px-6">
                                <dt class="text-slate-500">Status</dt>
                                <dd>
                                    @if ($archived)
                                        <span class="badge badge-slate">Archived {{ $team->archived_at->format('M j, Y') }}</span>
                                    @else
                                        <span class="badge badge-success">Active</span>
                                    @endif
                                </dd>
                            </div>
                            @if ($team->created_at)
                                <div class="flex items-center justify-between gap-3 px-5 py-3 sm:px-6">
                                    <dt class="text-slate-500">Created</dt>
                                    <dd class="text-slate-700">{{ $team->created_at->format('M j, Y') }}</dd>
                                </div>
                            @endif
                            <div class="px-5 py-3 sm:px-6">
                                <dt class="mb-1 text-slate-500">Description</dt>
                                <dd class="whitespace-pre-line text-slate-700">{{ $team->description ?: '—' }}</dd>
                            </div>
                        </dl>
                    </section>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
