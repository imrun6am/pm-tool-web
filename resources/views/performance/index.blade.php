@use('App\Support\ScoreColor')
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-4">
                <h2 class="page-title">Performance</h2>

                @if ($team)
                    <form method="GET" action="{{ route('performance.index') }}">
                        <input type="hidden" name="week" value="{{ $weekDate->toDateString() }}">
                        <select name="team" onchange="this.form.submit()" aria-label="Team"
                                class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            @foreach ($teams as $t)
                                <option value="{{ $t->id }}" @selected($team->id === $t->id)>{{ $t->name }}</option>
                            @endforeach
                        </select>
                    </form>

                    <div class="flex items-center gap-1">
                        <a href="{{ route('performance.index', ['team' => $team->id, 'week' => $prevWeek->toDateString()]) }}"
                           class="btn-secondary btn-sm !px-2" aria-label="Previous week">‹</a>
                        <span class="px-2 text-sm font-medium text-slate-600">{{ $overview['weekLabel'] }}</span>
                        <a href="{{ route('performance.index', ['team' => $team->id, 'week' => $nextWeek->toDateString()]) }}"
                           class="btn-secondary btn-sm !px-2" aria-label="Next week">›</a>
                        <a href="{{ route('performance.index', ['team' => $team->id]) }}" class="btn-ghost btn-sm ml-1">This week</a>
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('performance.evaluate', ['team' => $team?->id]) }}" class="btn-primary btn-sm">
                    <x-icon name="pencil" class="h-4 w-4" /> Evaluate
                </a>
                @can('manage-competencies')
                    <a href="{{ route('performance.competencies.index') }}" class="btn-ghost btn-sm">Competencies</a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container space-y-6">
            @if (! $team)
                <div class="card p-12 text-center text-sm text-slate-500">
                    No teams available. You can only see performance for teams you lead.
                </div>
            @else
                @php $o = $overview; $avg = $o['teamAverage']; @endphp

                {{-- KPI row --}}
                <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    {{-- Team average --}}
                    <div class="card card-pad">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs font-medium text-slate-500">Team average</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg text-white" style="background-color: {{ ScoreColor::hex($avg) }}">
                                <x-icon name="activity" class="h-4 w-4" />
                            </span>
                        </div>
                        <p class="mt-2 text-3xl font-semibold tracking-tight" style="color: {{ ScoreColor::hex($avg) }}">
                            {{ ScoreColor::fmt($avg) }}<span class="text-lg text-slate-300">/5</span>
                        </p>
                        <p class="mt-1 text-xs text-slate-400">{{ $avg !== null ? ScoreColor::pct($avg).'% · '.ScoreColor::label($avg) : 'Not yet evaluated' }}</p>
                    </div>

                    @include('partials.stat-tile', [
                        'label' => 'Evaluation coverage',
                        'value' => $o['coverage']['pct'] !== null ? $o['coverage']['pct'].'%' : '—',
                        'sub' => $o['coverage']['covered'].' of '.$o['coverage']['expected'].' ratings',
                        'icon' => 'list',
                        'tone' => ($o['coverage']['pct'] ?? 0) >= 80 ? 'emerald' : (($o['coverage']['pct'] ?? 0) >= 40 ? 'amber' : 'slate'),
                    ])

                    <div class="card card-pad">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs font-medium text-slate-500">Top performer</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                                <x-icon name="rocket" class="h-4 w-4" />
                            </span>
                        </div>
                        @if ($o['topPerformer'])
                            <p class="mt-2 truncate text-lg font-semibold text-slate-900">{{ $o['topPerformer']['member']->name }}</p>
                            <p class="mt-1">@include('partials.perf-score', ['value' => $o['topPerformer']['overall'], 'showLabel' => true])</p>
                        @else
                            <p class="mt-2 text-lg font-semibold text-slate-300">—</p>
                            <p class="mt-1 text-xs text-slate-400">No ratings yet</p>
                        @endif
                    </div>

                    @include('partials.stat-tile', [
                        'label' => 'Needs attention',
                        'value' => $o['needsAttention']->count(),
                        'sub' => 'below 3.0 or declining',
                        'icon' => 'pause',
                        'tone' => $o['needsAttention']->isNotEmpty() ? 'rose' : 'slate',
                    ])
                </div>

                <div class="grid gap-6 lg:grid-cols-3">
                    {{-- Leaderboard --}}
                    <div class="card card-pad lg:col-span-2">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900">Member leaderboard</h3>
                                <p class="mt-0.5 text-xs text-slate-400">Weighted score this week · click a member for their scorecard</p>
                            </div>
                        </div>

                        @if ($o['members']->isEmpty())
                            <p class="mt-6 text-sm text-slate-400">This team has no developer or QA members.</p>
                        @else
                            <ul class="mt-4 divide-y divide-slate-100">
                                @foreach ($o['leaderboard'] as $i => $row)
                                    <li class="flex items-center gap-3 py-2.5">
                                        <span class="w-5 flex-none text-center text-xs font-semibold text-slate-400">{{ $i + 1 }}</span>
                                        <a href="{{ route('performance.members.show', ['user' => $row['member']->id, 'team' => $team->id, 'week' => $weekDate->toDateString()]) }}"
                                           class="flex min-w-0 flex-1 items-center gap-2">
                                            <span class="flex h-8 w-8 flex-none items-center justify-center rounded-full bg-slate-100 text-[11px] font-semibold text-slate-600">
                                                {{ strtoupper(collect(explode(' ', $row['member']->name))->filter()->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('')) }}
                                            </span>
                                            <span class="min-w-0">
                                                <span class="block truncate text-sm font-medium text-slate-800 hover:text-brand-600">{{ $row['member']->name }}</span>
                                                <span class="block text-[11px] text-slate-400">
                                                    {{ $row['member']->roleLabel() }}
                                                    @if ($row['onLeave']) · <span class="text-sky-600">on leave</span>
                                                    @elseif ($row['declining']) · <span class="text-rose-500">declining</span>@endif
                                                </span>
                                            </span>
                                        </a>
                                        <div class="hidden h-2 w-32 flex-none overflow-hidden rounded-full bg-slate-100 sm:block">
                                            <div class="h-full rounded-full" style="width: {{ $row['overall'] !== null ? round($row['overall'] / 5 * 100) : 0 }}%; background-color: {{ ScoreColor::hex($row['overall']) }}"></div>
                                        </div>
                                        <span class="w-16 flex-none text-right">@include('partials.perf-score', ['value' => $row['overall']])</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    {{-- Category averages + trend --}}
                    <div class="space-y-6">
                        <div class="card card-pad">
                            <h3 class="text-sm font-semibold text-slate-900">By category</h3>
                            <p class="mt-0.5 text-xs text-slate-400">Team average per competency category</p>
                            <ul class="mt-4 space-y-3">
                                @foreach ($o['categoryAverages'] as $cat)
                                    <li class="flex items-center gap-3">
                                        <span class="w-20 flex-none truncate text-xs text-slate-600" title="{{ $cat['label'] }}">{{ $cat['label'] }}</span>
                                        <div class="relative h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                                            <div class="absolute inset-y-0 left-0 rounded-full" style="width: {{ $cat['value'] !== null ? round($cat['value'] / 5 * 100) : 0 }}%; background-color: {{ ScoreColor::hex($cat['value']) }}"></div>
                                        </div>
                                        <span class="w-8 flex-none text-right text-xs font-semibold text-slate-700 tabular">{{ ScoreColor::fmt($cat['value']) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        <div class="card card-pad">
                            <h3 class="text-sm font-semibold text-slate-900">Team trend</h3>
                            <p class="mt-0.5 text-xs text-slate-400">Average score · last {{ count($o['trend']) }} weeks</p>
                            <div class="mt-3">@include('partials.spark-line', ['points' => $o['trend'], 'max' => 5])</div>
                        </div>
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-3">
                    {{-- Needs attention --}}
                    <div class="card card-pad lg:col-span-2">
                        <h3 class="text-sm font-semibold text-slate-900">Needs attention</h3>
                        <p class="mt-0.5 text-xs text-slate-400">Members scoring below 3.0 this week, or trending down</p>
                        @if ($o['needsAttention']->isEmpty())
                            <p class="mt-6 text-sm text-slate-400">Nobody is flagged this week. 👍</p>
                        @else
                            <ul class="mt-4 space-y-2">
                                @foreach ($o['needsAttention'] as $row)
                                    <li class="flex items-center justify-between gap-3 rounded-lg bg-rose-50/50 px-3 py-2">
                                        <a href="{{ route('performance.members.show', ['user' => $row['member']->id, 'team' => $team->id, 'week' => $weekDate->toDateString()]) }}"
                                           class="text-sm font-medium text-slate-800 hover:text-brand-600">{{ $row['member']->name }}</a>
                                        <div class="flex items-center gap-2 text-xs text-slate-500">
                                            @if ($row['declining'] && $row['prevOverall'] !== null)
                                                <span class="text-rose-500">↓ from {{ ScoreColor::fmt($row['prevOverall']) }}</span>
                                            @endif
                                            @include('partials.perf-score', ['value' => $row['overall']])
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        @if (! empty($o['coverage']['unrated']))
                            <p class="mt-4 border-t border-slate-100 pt-3 text-xs text-slate-400">
                                Still unrated this week: <span class="text-slate-600">{{ implode(', ', $o['coverage']['unrated']) }}</span>
                            </p>
                        @endif
                    </div>

                    {{-- Tasksheet output (objective, separate from ratings) --}}
                    <div class="card card-pad">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-slate-900">Team output</h3>
                            <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-slate-400">Tasksheet</span>
                        </div>
                        <div class="mt-3 flex items-baseline gap-4">
                            <div>
                                <p class="text-2xl font-semibold text-slate-900">{{ $o['tasksheet']['workPoints'] }}</p>
                                <p class="text-xs text-slate-400">work points</p>
                            </div>
                            <div>
                                <p class="text-2xl font-semibold text-slate-900">{{ $o['tasksheet']['ticketCount'] }}</p>
                                <p class="text-xs text-slate-400">tickets</p>
                            </div>
                        </div>
                        @php $tMax = collect($o['tasksheet']['daily'])->max('value') ?: 0; @endphp
                        <div class="mt-4 flex h-20 items-end gap-1.5">
                            @foreach ($o['tasksheet']['daily'] as $d)
                                <div class="flex flex-1 flex-col items-center justify-end gap-1" title="{{ $d['label'] }}: {{ $d['value'] }} work points">
                                    <div class="w-full max-w-[1.75rem] rounded-t bg-brand-400" style="height: {{ $tMax > 0 ? max((int) round($d['value'] / $tMax * 100), $d['value'] > 0 ? 8 : 0) : 0 }}%"></div>
                                    <span class="text-[10px] text-slate-400">{{ $d['label'][0] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
