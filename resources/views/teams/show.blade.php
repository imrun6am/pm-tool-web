<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="inline-block h-4 w-4 rounded-full" style="background-color: {{ $team->color }}"></span>
                <h2 class="page-title">{{ $team->name }}</h2>
                @if ($team->isArchived())
                    <span class="rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-medium text-slate-700">Archived</span>
                @endif
            </div>
            @if (auth()->user()->canManageWorkspace())
                <a href="{{ route('teams.edit', $team) }}" class="btn-secondary btn-sm">
                    <x-icon name="pencil" class="h-4 w-4" />
                    Edit team
                </a>
            @endif
        </div>
    </x-slot>

    @php
        $conflictCount = collect($conflicts)->filter()->count();
        $busyThrough = $releases->max('end_date');
        $canManage = auth()->user()->canManageTeamMembers();
        $canManageTeam = auth()->user()->canManageWorkspace();
        $initials = fn ($name) => strtoupper(\Illuminate\Support\Str::of($name)->explode(' ')->map(fn ($p) => $p[0] ?? '')->take(2)->implode(''));
    @endphp

    <div class="py-8">
        <div class="app-container space-y-6">

            {{-- ============ KPI band ============ --}}
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                @include('partials.stat-tile', ['label' => 'Members', 'value' => $team->members->count(), 'sub' => 'on this team', 'icon' => 'users', 'tone' => 'sky'])
                @include('partials.stat-tile', ['label' => 'Releases', 'value' => $releases->count(), 'sub' => 'owned by team', 'icon' => 'rocket', 'tone' => 'brand'])
                @include('partials.stat-tile', ['label' => 'Booked through', 'value' => $busyThrough ? $busyThrough->format('M j') : '—', 'sub' => $busyThrough ? 'next free ' . $busyThrough->copy()->addDay()->format('M j, Y') : 'no releases yet', 'icon' => 'calendar', 'tone' => 'emerald'])
                @include('partials.stat-tile', ['label' => 'Overlapping', 'value' => $conflictCount, 'sub' => 'double-booked windows', 'icon' => 'activity', 'tone' => $conflictCount ? 'amber' : 'slate'])
            </div>

            {{-- ============ Two-column body ============ --}}
            <div class="grid gap-6 lg:grid-cols-3">

                {{-- ---------- LEFT: main content ---------- --}}
                <div class="space-y-6 lg:col-span-2">

                    {{-- Members --}}
                    <div class="card overflow-hidden">
                        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                            <h3 class="text-sm font-semibold text-slate-700">Members ({{ $team->members->count() }})</h3>
                        </div>

                        @if ($canManage)
                            <form method="POST" action="{{ route('teams.members.store', $team) }}"
                                  class="flex flex-wrap items-end gap-3 border-b border-slate-100 px-6 py-4">
                                @csrf
                                <div class="min-w-[220px] flex-1">
                                    <label for="user_id" class="eyebrow">Add a member</label>
                                    <select id="user_id" name="user_id" required class="field-input">
                                        <option value="">— Select a person —</option>
                                        @foreach ($assignableUsers as $user)
                                            <option value="{{ $user->id }}">{{ $user->name }} · {{ $user->roleLabel() }}</option>
                                        @endforeach
                                    </select>
                                    @error('user_id') <p class="field-error">{{ $message }}</p> @enderror
                                </div>
                                <button class="btn-primary btn-sm" @disabled($assignableUsers->isEmpty())>Add member</button>
                            </form>
                        @endif

                        @if ($team->members->isEmpty())
                            <div class="px-6 py-8 text-center text-sm text-slate-400">No members yet.</div>
                        @else
                            <ul class="divide-y divide-slate-100">
                                @foreach ($team->members as $member)
                                    <li class="flex items-center gap-3 px-6 py-3">
                                        <span class="flex h-8 w-8 flex-none items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700">
                                            {{ $initials($member->name) }}
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <p class="flex items-center gap-2 truncate text-sm font-medium text-slate-800">
                                                {{ $member->name }}
                                                @if ($member->id === $team->team_lead_id)
                                                    <span class="rounded-full bg-cyan-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-cyan-700">Lead</span>
                                                @endif
                                            </p>
                                            <p class="truncate text-xs text-slate-400">{{ $member->email }}</p>
                                        </div>
                                        <span class="hidden sm:block">@include('partials.role-badge', ['role' => $member->role])</span>
                                        @if ($canManage)
                                            <form method="POST" action="{{ route('teams.members.destroy', [$team, $member]) }}"
                                                  data-confirm="Remove {{ $member->name }} from this team?" data-confirm-verb="Remove">
                                                @csrf @method('DELETE')
                                                <button class="text-xs text-slate-400 hover:text-rose-600">Remove</button>
                                            </form>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    {{-- Schedule --}}
                    <div class="card overflow-hidden">
                        <div class="border-b border-slate-100 px-6 py-4">
                            <h3 class="text-sm font-semibold text-slate-700">Schedule (chronological)</h3>
                        </div>
                        @include('partials.releases-table', ['releases' => $releases, 'conflicts' => $conflicts, 'showProject' => true, 'showTeam' => false])
                    </div>
                </div>

                {{-- ---------- RIGHT: sidebar ---------- --}}
                <div class="space-y-6">

                    {{-- Team lead --}}
                    <div class="card overflow-hidden">
                        <div class="border-b border-slate-100 px-6 py-4">
                            <h3 class="text-sm font-semibold text-slate-700">Team lead</h3>
                        </div>
                        <div class="px-6 py-5">
                            @if ($team->teamLead)
                                <div class="flex items-center gap-3">
                                    <span class="flex h-11 w-11 flex-none items-center justify-center rounded-full bg-cyan-100 text-sm font-semibold text-cyan-700">
                                        {{ $initials($team->teamLead->name) }}
                                    </span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-900">{{ $team->teamLead->name }}</p>
                                        <p class="truncate text-xs text-slate-400">{{ $team->teamLead->email }}</p>
                                        <span class="mt-1 inline-block">@include('partials.role-badge', ['role' => $team->teamLead->role, 'variant' => 'text'])</span>
                                    </div>
                                </div>
                            @else
                                <div class="flex items-center gap-3 text-slate-400">
                                    <span class="flex h-11 w-11 flex-none items-center justify-center rounded-full bg-slate-100">
                                        <x-icon name="user" class="h-5 w-5" />
                                    </span>
                                    <p class="text-sm">No lead assigned yet.</p>
                                </div>
                            @endif

                            @if ($canManageTeam)
                                <form method="POST" action="{{ route('teams.lead.update', $team) }}" class="mt-4 space-y-2">
                                    @csrf @method('PUT')
                                    <label for="team_lead_id" class="eyebrow">Assign lead</label>
                                    <select id="team_lead_id" name="team_lead_id" class="field-input">
                                        <option value="">— No lead —</option>
                                        @foreach ($leadCandidates as $candidate)
                                            <option value="{{ $candidate->id }}" @selected($candidate->id === $team->team_lead_id)>
                                                {{ $candidate->name }} · {{ $candidate->roleLabel() }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <p class="text-xs text-slate-400">Any user is eligible — role doesn’t matter.</p>
                                    <button class="btn-primary btn-sm w-full">Save lead</button>
                                </form>
                            @endif
                        </div>
                    </div>

                    {{-- About --}}
                    <div class="card overflow-hidden">
                        <div class="border-b border-slate-100 px-6 py-4">
                            <h3 class="text-sm font-semibold text-slate-700">About</h3>
                        </div>
                        <dl class="divide-y divide-slate-100 text-sm">
                            <div class="flex items-center justify-between px-6 py-3">
                                <dt class="text-slate-500">Color</dt>
                                <dd class="flex items-center gap-2 text-slate-700">
                                    <span class="inline-block h-3.5 w-3.5 rounded-full" style="background-color: {{ $team->color }}"></span>
                                    <span class="font-mono text-xs uppercase">{{ $team->color }}</span>
                                </dd>
                            </div>
                            <div class="flex items-center justify-between px-6 py-3">
                                <dt class="text-slate-500">Status</dt>
                                <dd>
                                    @if ($team->isArchived())
                                        <span class="rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-medium text-slate-700">Archived</span>
                                    @else
                                        <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">Active</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="px-6 py-3">
                                <dt class="mb-1 text-slate-500">Description</dt>
                                <dd class="text-slate-700">{{ $team->description ?: '—' }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
