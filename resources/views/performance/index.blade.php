@use('App\Support\ScoreColor')
<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Performance" description="Weekly competency scores and tasksheet output for the teams you lead.">
            <x-slot name="actions">
                @can('manage-competencies')
                    <a href="{{ route('performance.competencies.index') }}" class="btn-secondary btn-sm">Competencies</a>
                @endcan
                <a href="{{ route('performance.evaluate', ['team' => $team?->id]) }}" class="btn-primary btn-sm">
                    <x-icon name="pencil-square" variant="outline" class="h-4 w-4" /> Evaluate
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container space-y-4">
            @if ($team)
                <x-filter-bar :action="route('performance.index')">
                    <input type="hidden" name="week" value="{{ $weekDate->toDateString() }}">
                    <label for="team" class="filter-label">Team</label>
                    <select id="team" name="team" class="filter-select max-w-[14rem]">
                        @foreach ($teams as $t)
                            <option value="{{ $t->id }}" @selected($team->id === $t->id)>{{ $t->name }}</option>
                        @endforeach
                    </select>

                    {{-- Week navigation (plain links; the team select above auto-submits) --}}
                    <div class="flex items-center gap-1">
                        <a href="{{ route('performance.index', ['team' => $team->id, 'week' => $prevWeek->toDateString()]) }}"
                           class="btn-secondary btn-sm btn-icon" aria-label="Previous week">‹</a>
                        <span class="tabular whitespace-nowrap px-2 text-sm font-medium text-slate-700">{{ $overview['weekLabel'] }}</span>
                        <a href="{{ route('performance.index', ['team' => $team->id, 'week' => $nextWeek->toDateString()]) }}"
                           class="btn-secondary btn-sm btn-icon" aria-label="Next week">›</a>
                        <a href="{{ route('performance.index', ['team' => $team->id]) }}" class="btn-ghost btn-sm">This week</a>
                    </div>
                </x-filter-bar>
            @endif

            @if (! $team)
                <div class="card">
                    <x-empty-state icon="chart-bar" title="No teams available."
                                   description="You can only see performance for teams you lead." />
                </div>
            @else
                @php $o = $overview; $avg = $o['teamAverage']; @endphp

                {{-- KPI strip --}}
                @php
                    $covPct = $o['coverage']['pct'];
                    $attention = $o['needsAttention']->count();
                    $outOfFive = new \Illuminate\Support\HtmlString(e(ScoreColor::fmt($avg)).'<span class="text-base font-medium text-slate-300">/5</span>');
                @endphp
                <div class="stat-strip">
                    <x-stat label="Team average" :value="$outOfFive"
                            :sub="$avg !== null ? ScoreColor::pct($avg).'% · '.ScoreColor::label($avg) : 'Not yet evaluated'"
                            icon="chart-bar" :tone="ScoreColor::tone($avg)">
                        <div class="mt-2">@include('partials.delta', ['change' => $o['averageChange']['change'], 'versus' => 'last week'])</div>
                    </x-stat>
                    <x-stat label="Evaluation coverage" :value="$covPct !== null ? $covPct.'%' : '—'"
                            :sub="$o['coverage']['covered'].' of '.$o['coverage']['expected'].' ratings'"
                            icon="clipboard-check" :tone="($covPct ?? 0) >= 80 ? 'emerald' : (($covPct ?? 0) >= 40 ? 'amber' : 'slate')" />
                    @if ($o['topPerformer'])
                        <x-stat label="Top performer" :value="$o['topPerformer']['member']->name" icon="rocket" tone="emerald"
                                class="[&_.stat-value]:truncate [&_.stat-value]:text-xl">
                            <p class="mt-1.5">@include('partials.perf-score', ['value' => $o['topPerformer']['overall'], 'showLabel' => true])</p>
                        </x-stat>
                    @else
                        <x-stat label="Top performer" value="—" sub="No ratings yet" icon="rocket" tone="slate" />
                    @endif
                    <x-stat label="Needs attention" :value="$attention" sub="below 3.0 or declining"
                            icon="exclamation-circle" tone="slate" :alert="$attention > 0" />
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
                                        @if ($row['vsTeam'] !== null)
                                            <span class="hidden w-20 flex-none text-right text-[11px] tabular md:block {{ $row['vsTeam'] >= 0 ? 'text-success-700' : 'text-slate-500' }}"
                                                  title="Against the team average">{{ $row['vsTeam'] >= 0 ? '+' : '−' }}{{ ScoreColor::fmt(abs($row['vsTeam'])) }} vs team</span>
                                        @endif
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

                @include('performance._team_depth')
            @endif
        </div>
    </div>
</x-app-layout>
