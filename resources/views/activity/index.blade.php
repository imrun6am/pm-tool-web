@use('App\Services\ActivityInsights')
@use('Illuminate\Support\Str')
<x-app-layout>
    @php
        $a = $analysis;
        $k = $a['kpis'];
        $w = $a['window'];
        $versus = 'previous '.$w['days'].' days';

        // Scope filters carry into every link; slice filters are what a drill sets.
        $scope = collect($filters)->only(['days', 'causer_id', 'team_id', 'project_id', 'release_id'])->filter()->all();
        $drill = fn (array $slice) => route('activity.index', array_filter([...$scope, ...$slice], fn ($v) => $v !== null)).'#feed';
        $hasSlice = $filters['event'] || $filters['subject_type'] || $filters['from'] || $filters['to'] || $filters['release_id'];
        $hasFilters = $hasSlice || $filters['causer_id'] || $filters['team_id'] || $filters['project_id'];

        // One colour language for events across the donut, people mix and feed.
        $eventMeta = [
            'created' => ['label' => 'Created', 'hex' => '#10b981', 'bg' => 'bg-emerald-500'],
            'updated' => ['label' => 'Updated', 'hex' => '#2f7cf8', 'bg' => 'bg-brand-500'],
            'deleted' => ['label' => 'Deleted', 'hex' => '#f43f5e', 'bg' => 'bg-rose-500'],
        ];
        $fmtDwell = fn (?float $h) => $h === null ? '—' : ($h >= 48 ? rtrim(rtrim(number_format($h / 24, 1), '0'), '.').'d' : rtrim(rtrim(number_format($h, 1), '0'), '.').'h');
        $rampFor = function (int $c, int $max) {
            if ($c === 0 || $max === 0) return 'bg-slate-100';
            $r = $c / $max;
            return $r > 0.75 ? 'bg-brand-700' : ($r > 0.5 ? 'bg-brand-500' : ($r > 0.25 ? 'bg-brand-300' : 'bg-brand-100'));
        };
    @endphp

    <x-slot name="header">
        <x-page-header title="Activity" :description="'Who changed what, when, and how work flowed · '.$w['label'].' ('.$w['start']->format('M j').' – '.$w['end']->format('M j').')'">
            <x-slot name="actions">
                <form method="GET" action="{{ route('activity.index') }}" x-data="filterBar" @change="onChange($event)">
                    @foreach (collect($scope)->except('days') as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <fieldset class="segmented">
                        <legend class="sr-only">Analysis period</legend>
                        @foreach (ActivityInsights::PERIODS as $days => $label)
                            <label><input type="radio" name="days" value="{{ $days }}" @checked($filters['days'] === $days)>{{ $days }}d</label>
                        @endforeach
                    </fieldset>
                    <button type="submit" class="sr-only">Apply period</button>
                </form>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container space-y-6">

            {{-- ============================ FILTERS ============================ --}}
            <x-filter-bar :action="route('activity.index')" :active="$hasFilters">
                <input type="hidden" name="days" value="{{ $filters['days'] }}">
                <label for="causer_id" class="filter-label">Person</label>
                <select id="causer_id" name="causer_id" class="filter-select max-w-[12rem]">
                    <option value="">Everyone</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}" @selected($filters['causer_id'] === $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
                <label for="team_id" class="filter-label">Team</label>
                <select id="team_id" name="team_id" class="filter-select max-w-[11rem]">
                    <option value="">All teams</option>
                    @foreach ($teams as $t)
                        <option value="{{ $t->id }}" @selected($filters['team_id'] === $t->id)>{{ $t->name }}</option>
                    @endforeach
                </select>
                <label for="project_id" class="filter-label">Project</label>
                <select id="project_id" name="project_id" class="filter-select max-w-[11rem]">
                    <option value="">All projects</option>
                    @foreach ($projects as $p)
                        <option value="{{ $p->id }}" @selected($filters['project_id'] === $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
                <label for="event" class="filter-label">Event</label>
                <select id="event" name="event" class="filter-select">
                    <option value="">All events</option>
                    @foreach ($eventMeta as $val => $meta)
                        <option value="{{ $val }}" @selected($filters['event'] === $val)>{{ $meta['label'] }}</option>
                    @endforeach
                </select>
                <label for="subject_type" class="filter-label">Record</label>
                <select id="subject_type" name="subject_type" class="filter-select max-w-[11rem]">
                    <option value="">All records</option>
                    @foreach ($typeOptions as $type)
                        <option value="{{ $type['type'] }}" @selected($filters['subject_type'] === $type['type'])>{{ $type['label'] }}</option>
                    @endforeach
                </select>
                @if ($filters['release_id'])<input type="hidden" name="release_id" value="{{ $filters['release_id'] }}">@endif
            </x-filter-bar>

            {{-- ============================ KPIs ============================ --}}
            <div class="stat-strip">
                <x-stat label="Events" :value="number_format($k['events']['current'])" icon="chart-bar" tone="brand"
                        :sub="$k['perDay'].' a day on average'">
                    <div class="mt-2">@include('partials.delta', ['change' => $k['events']['change'], 'versus' => $versus])</div>
                </x-stat>
                <x-stat label="Active people" :value="$k['people']['current']" icon="user-group" tone="sky" sub="made at least one change">
                    <div class="mt-2">@include('partials.delta', ['change' => $k['people']['change'], 'versus' => $versus])</div>
                </x-stat>
                <x-stat label="Workflow moves" :value="$a['flow']['moves']" icon="view-columns" tone="violet"
                        :sub="$a['flow']['milestones']['done'].' to Done · '.$a['flow']['milestones']['rework'].' to Recheck'" />
                <x-stat label="Deletions" :value="$k['deletions']['current']" icon="trash" tone="rose"
                        :sub="$stats['total'] ? number_format($stats['total']).' events all time' : 'Nothing recorded yet'">
                    <div class="mt-2">@include('partials.delta', ['change' => $k['deletions']['change'], 'versus' => $versus, 'lowerIsBetter' => true])</div>
                </x-stat>
            </div>

            @if ($k['events']['current'] === 0)
                <div class="card">
                    <x-empty-state icon="clock" title="No activity in this window."
                                   description="Try a longer period or clear the person, team or project filter.">
                        @if ($hasFilters)<a href="{{ route('activity.index') }}" class="btn-secondary btn-sm">Clear all filters</a>@endif
                    </x-empty-state>
                </div>
            @else
                {{-- ============================ VOLUME ============================ --}}
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <section class="card card-pad lg:col-span-2" aria-labelledby="volume-title">
                        <h2 id="volume-title" class="card-title">Volume</h2>
                        <p class="card-subtitle">Events per {{ $w['days'] > 30 ? 'week' : 'day' }} · click a bar to see those entries</p>
                        @include('partials.column-chart', [
                            'label' => 'Events per '.($w['days'] > 30 ? 'week' : 'day'),
                            'bars' => collect($a['volume'])->map(fn ($b) => [
                                'label' => $b['label'],
                                'value' => $b['count'],
                                'current' => $b['current'],
                                'highlight' => false,
                                'title' => number_format($b['count']).' events · '.($b['start']->eq($b['end']) ? $b['start']->format('D, M j') : $b['start']->format('M j').' – '.$b['end']->format('M j')),
                                'href' => $drill(['from' => $b['start']->toDateString(), 'to' => $b['end']->toDateString()]),
                            ])->all(),
                        ])
                    </section>

                    <section class="card card-pad" aria-labelledby="events-title">
                        <h2 id="events-title" class="card-title">By event</h2>
                        <p class="card-subtitle">Click a row to filter the feed</p>
                        <div class="mt-5">
                            @include('partials.donut', [
                                'label' => 'Events by kind',
                                'center' => number_format($k['events']['current']),
                                'caption' => 'events',
                                'segments' => collect($eventMeta)->map(fn ($m, $key) => [
                                    'label' => $m['label'], 'value' => $a['byEvent'][$key], 'color' => $m['hex'],
                                    'href' => $drill(['event' => $key]),
                                ])->values()->all(),
                            ])
                        </div>
                    </section>
                </div>

                {{-- ============================ BREAKDOWNS ============================ --}}
                @php
                    $ranked = [
                        ['title' => 'By record type', 'sub' => 'What changed', 'rows' => collect($a['byType'])->map(fn ($r) => [
                            'label' => $r['label'], 'count' => $r['count'], 'color' => null, 'href' => $drill(['subject_type' => $r['type']])])],
                        ['title' => 'By team', 'sub' => 'Through the release a change belongs to', 'rows' => collect($a['byTeam'])->map(fn ($r) => [
                            'label' => $r['name'], 'count' => $r['count'], 'color' => $r['color'], 'href' => $r['id'] ? $drill(['team_id' => $r['id']]) : null])],
                        ['title' => 'Busiest releases', 'sub' => 'Most changes in this window', 'rows' => collect($a['byRelease'])->map(fn ($r) => [
                            'label' => $r['name'], 'count' => $r['count'], 'color' => $r['color'], 'href' => $drill(['release_id' => $r['id']])])],
                    ];
                @endphp
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    @foreach ($ranked as $panel)
                        <section class="card card-pad">
                            <h2 class="card-title">{{ $panel['title'] }}</h2>
                            <p class="card-subtitle">{{ $panel['sub'] }}</p>
                            @if ($panel['rows']->isEmpty())
                                <p class="mt-6 text-sm text-slate-500">Nothing in this window.</p>
                            @else
                                @php $max = max($panel['rows']->max('count'), 1); @endphp
                                <ul class="mt-4 space-y-2.5">
                                    @foreach ($panel['rows'] as $row)
                                        <li>
                                            <{{ $row['href'] ? 'a' : 'div' }} @if ($row['href']) href="{{ $row['href'] }}" @endif
                                                @class(['block rounded -mx-1 px-1 py-0.5', 'hover:bg-slate-50' => $row['href']])>
                                                <span class="flex items-center justify-between gap-3 text-sm">
                                                    <span class="flex min-w-0 items-center gap-2">
                                                        @if ($row['color'])<span class="h-2.5 w-2.5 flex-none rounded-full" style="background-color: {{ $row['color'] }}"></span>@endif
                                                        <span class="truncate text-slate-700">{{ $row['label'] }}</span>
                                                    </span>
                                                    <span class="tabular flex-none font-semibold text-slate-900">{{ number_format($row['count']) }}</span>
                                                </span>
                                                <span class="mt-1 block h-1.5 overflow-hidden rounded-full bg-slate-100">
                                                    <span class="block h-full rounded-full bg-brand-500" style="width: {{ max(3, round($row['count'] / $max * 100)) }}%"></span>
                                                </span>
                                            </{{ $row['href'] ? 'a' : 'div' }}>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </section>
                    @endforeach
                </div>

                {{-- ============================ PEOPLE + RHYTHM ============================ --}}
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
                    <section class="card lg:col-span-2" aria-labelledby="people-title">
                        <div class="card-header">
                            <div>
                                <h2 id="people-title" class="card-title">Most active people</h2>
                                <p class="card-subtitle">Changes in this window and their mix</p>
                            </div>
                        </div>
                        @if (empty($a['people']))
                            <p class="px-5 py-6 text-sm text-slate-500 sm:px-6">No attributed changes in this window.</p>
                        @else
                            @php $pMax = max(collect($a['people'])->max('total'), 1); @endphp
                            <ul class="divide-y divide-slate-100">
                                @foreach ($a['people'] as $p)
                                    <li>
                                        <a href="{{ $drill(['causer_id' => $p['user']->id]) }}" class="block px-5 py-2.5 hover:bg-slate-50/80 sm:px-6"
                                           title="{{ $p['created'] }} created · {{ $p['updated'] }} updated · {{ $p['deleted'] }} deleted">
                                            <span class="flex items-center gap-2.5">
                                                <x-avatar :user="$p['user']" size="sm" />
                                                <span class="min-w-0 flex-1 truncate text-sm text-slate-800">{{ $p['user']->name }}</span>
                                                <span class="tabular text-sm font-semibold text-slate-900">{{ number_format($p['total']) }}</span>
                                            </span>
                                            {{-- stacked event mix, scaled to the busiest person --}}
                                            <span class="mt-1.5 flex h-1.5 gap-px overflow-hidden rounded-full bg-slate-100" style="width: {{ max(6, round($p['total'] / $pMax * 100)) }}%">
                                                @foreach ($eventMeta as $key => $meta)
                                                    @if ($p[$key] > 0)<span class="{{ $meta['bg'] }}" style="flex: {{ $p[$key] }}"></span>@endif
                                                @endforeach
                                            </span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                            <p class="flex flex-wrap gap-x-3 gap-y-1 border-t border-slate-100 px-5 py-2.5 text-[11px] text-slate-500 sm:px-6">
                                @foreach ($eventMeta as $meta)
                                    <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full {{ $meta['bg'] }}"></span>{{ $meta['label'] }}</span>
                                @endforeach
                            </p>
                        @endif
                    </section>

                    <section class="card card-pad min-w-0 lg:col-span-3" aria-labelledby="rhythm-title">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <h2 id="rhythm-title" class="card-title">Weekly rhythm</h2>
                                <p class="card-subtitle">Changes by weekday and hour ({{ config('app.timezone') }})</p>
                            </div>
                            @if ($a['rhythm']['peak'])
                                <p class="text-right text-xs text-slate-500">Busiest slot
                                    <span class="block text-sm font-semibold text-slate-900">{{ $a['rhythm']['peak']['day'] }} {{ sprintf('%02d:00', $a['rhythm']['peak']['hour']) }}</span>
                                </p>
                            @endif
                        </div>
                        @php $rMax = $a['rhythm']['max']; @endphp
                        <div class="mt-4 overflow-x-auto pb-1">
                            <div class="inline-grid min-w-full gap-[3px]" style="grid-template-columns: 2.25rem repeat(24, minmax(0.75rem, 1fr))" role="img"
                                 aria-label="Activity by weekday and hour; busiest {{ $a['rhythm']['peak'] ? $a['rhythm']['peak']['day'].' at '.$a['rhythm']['peak']['hour'].':00' : 'none' }}">
                                <span></span>
                                @for ($h = 0; $h < 24; $h++)
                                    <span class="text-center text-[9px] text-slate-400" aria-hidden="true">{{ $h % 3 === 0 ? $h : '' }}</span>
                                @endfor
                                @foreach ($a['rhythm']['grid'] as $row)
                                    <span class="text-[10px] leading-4 text-slate-500" aria-hidden="true">{{ $row['day'] }}</span>
                                    @foreach ($row['hours'] as $h => $c)
                                        <span class="h-4 rounded-[3px] ring-1 ring-inset ring-slate-900/5 {{ $rampFor($c, $rMax) }}"
                                              title="{{ $row['day'] }} {{ sprintf('%02d:00', $h) }}: {{ $c }} {{ Str::plural('change', $c) }}" aria-hidden="true"></span>
                                    @endforeach
                                @endforeach
                            </div>
                        </div>
                        <div class="mt-3 flex items-center justify-end gap-1.5 text-[11px] text-slate-500" aria-hidden="true">
                            Fewer
                            @foreach (['bg-slate-100', 'bg-brand-100', 'bg-brand-300', 'bg-brand-500', 'bg-brand-700'] as $shade)
                                <span class="h-3 w-3 rounded-[3px] ring-1 ring-inset ring-slate-900/5 {{ $shade }}"></span>
                            @endforeach
                            More
                        </div>
                    </section>
                </div>

                {{-- ============================ WORKFLOW + DELETIONS ============================ --}}
                @php $f = $a['flow']; @endphp
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <section class="card card-pad lg:col-span-2" aria-labelledby="flow-title">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <h2 id="flow-title" class="card-title">Task workflow</h2>
                                <p class="card-subtitle">Status moves on the board in this window, and how long work waited in each status</p>
                            </div>
                            <a href="{{ $drill(['subject_type' => 'task', 'event' => 'updated']) }}" class="text-xs font-medium text-brand-700 hover:text-brand-800">Task changes →</a>
                        </div>
                        @if ($f['moves'] === 0)
                            <p class="mt-6 text-sm text-slate-500">No task moved status in this window.</p>
                        @else
                            <dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-5">
                                @foreach ([
                                    ['Started', $f['milestones']['started'], 'To Do → In Progress', false],
                                    ['To review', $f['milestones']['review'], 'moved into In Review', false],
                                    ['Rework', $f['milestones']['rework'], 'sent to Recheck', $f['milestones']['rework'] > 0],
                                    ['Done', $f['milestones']['done'], 'finished', false],
                                    ['Reopened', $f['milestones']['reopened'], 'out of Done/Archive', false],
                                ] as [$label, $value, $hint, $warn])
                                    <div @class(['rounded-lg px-3 py-2.5 ring-1 ring-inset', 'bg-warning-50 ring-warning-600/20' => $warn, 'bg-slate-50 ring-slate-200' => ! $warn]) title="{{ $hint }}">
                                        <dd @class(['tabular text-xl font-semibold leading-none', 'text-warning-700' => $warn, 'text-slate-900' => ! $warn])>{{ $value }}</dd>
                                        <dt @class(['mt-1 text-xs', 'text-warning-800' => $warn, 'text-slate-500' => ! $warn])>{{ $label }}</dt>
                                    </div>
                                @endforeach
                            </dl>
                            @if ($f['reworkRate'] !== null)
                                <p class="mt-2 text-xs text-slate-500">Rework rate: <span class="font-semibold text-slate-800">{{ $f['reworkRate'] }}%</span> of moves into Done or Recheck went to Recheck.</p>
                            @endif

                            <div class="mt-5 grid gap-6 md:grid-cols-2">
                                <div>
                                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Most common moves</h3>
                                    @php $tMax = max(collect($f['transitions'])->max('count'), 1); @endphp
                                    <ul class="mt-2 space-y-2">
                                        @foreach ($f['transitions'] as $t)
                                            <li>
                                                <div class="flex items-center justify-between gap-3 text-sm">
                                                    <span class="truncate text-slate-700">{{ $t['label'] }}</span>
                                                    <span class="tabular font-semibold text-slate-900">{{ $t['count'] }}</span>
                                                </div>
                                                <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                                    <div @class(['h-full rounded-full', 'bg-warning-500' => $t['to'] === 'recheck', 'bg-success-500' => $t['to'] === 'done', 'bg-brand-500' => ! in_array($t['to'], ['recheck', 'done'])])
                                                         style="width: {{ max(3, round($t['count'] / $tMax * 100)) }}%"></div>
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                                <div>
                                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Average time in status</h3>
                                    @php $dMax = max(collect($f['dwell'])->max('avgHours') ?? 0, 1); @endphp
                                    <ul class="mt-2 space-y-2">
                                        @foreach ($f['dwell'] as $d)
                                            <li title="{{ $d['samples'] }} {{ Str::plural('move', $d['samples']) }} measured">
                                                <div class="flex items-center justify-between gap-3 text-sm">
                                                    <span class="text-slate-700">{{ $d['label'] }}</span>
                                                    <span class="tabular font-semibold text-slate-900">{{ $fmtDwell($d['avgHours']) }}</span>
                                                </div>
                                                <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                                    <div class="h-full rounded-full bg-violet-500" style="width: {{ $d['avgHours'] ? max(3, round($d['avgHours'] / $dMax * 100)) : 0 }}%"></div>
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                    <p class="mt-2 text-[11px] text-slate-400">From entering a status to leaving it, for moves in this window.</p>
                                </div>
                            </div>
                        @endif
                    </section>

                    <section class="card" aria-labelledby="deletions-title">
                        <div class="card-header">
                            <div>
                                <h2 id="deletions-title" class="card-title">Recent deletions</h2>
                                <p class="card-subtitle">Removed records worth a second look</p>
                            </div>
                            @if ($a['deletions']->isNotEmpty())
                                <a href="{{ $drill(['event' => 'deleted']) }}" class="text-xs font-medium text-brand-700 hover:text-brand-800">All →</a>
                            @endif
                        </div>
                        @if ($a['deletions']->isEmpty())
                            <p class="px-5 py-6 text-sm text-slate-500 sm:px-6">Nothing was deleted in this window.</p>
                        @else
                            <ul class="divide-y divide-slate-100">
                                @foreach ($a['deletions'] as $del)
                                    <li class="px-5 py-2.5 sm:px-6">
                                        <p class="truncate text-sm text-slate-800" title="{{ $del->description }}">{{ $del->description }}</p>
                                        <p class="mt-0.5 text-xs text-slate-500">{{ $del->causer?->name ?? 'System' }} · {{ $del->created_at->diffForHumans() }}</p>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                </div>
            @endif

            {{-- ============================ FEED ============================ --}}
            <section id="feed" class="card scroll-mt-24" aria-labelledby="feed-title">
                <div class="card-header flex-wrap">
                    <div>
                        <h2 id="feed-title" class="card-title">Activity log</h2>
                        <p class="card-subtitle">{{ number_format($activities->total()) }} {{ Str::plural('entry', $activities->total()) }}{{ $filters['from'] ? '' : ' in '.Str::lower($w['label']) }}</p>
                    </div>
                    @if ($hasSlice)
                        <div class="flex flex-wrap items-center gap-1.5">
                            @if ($filters['from'])<span class="badge badge-brand">{{ \Illuminate\Support\Carbon::parse($filters['from'])->format('M j') }}{{ $filters['to'] && $filters['to'] !== $filters['from'] ? ' – '.\Illuminate\Support\Carbon::parse($filters['to'])->format('M j') : '' }}</span>@endif
                            @if ($filters['event'])<span class="badge badge-brand">{{ $eventMeta[$filters['event']]['label'] }}</span>@endif
                            @if ($filters['subject_type'])<span class="badge badge-brand">{{ Str::headline($filters['subject_type']) }}</span>@endif
                            @if ($release)<span class="badge badge-brand">{{ $release->name }}</span>@endif
                            <a href="{{ route('activity.index', collect($scope)->except('release_id')->all()) }}#feed" class="btn-ghost btn-sm">Clear drill-down</a>
                        </div>
                    @endif
                </div>
                @if ($activities->isEmpty())
                    @if ($hasFilters)
                        <x-empty-state icon="funnel" title="No activity matches these filters."
                                       description="Try another person, event type or a longer period.">
                            <a href="{{ route('activity.index') }}" class="btn-secondary btn-sm">Clear all filters</a>
                        </x-empty-state>
                    @else
                        <x-empty-state icon="clock" title="No activity recorded yet."
                                       description="Changes to releases, tasks and other records will appear here as they happen." />
                    @endif
                @else
                    <div class="px-6 py-2">
                        @include('partials.activity-list', ['activities' => $activities])
                    </div>
                @endif
            </section>
            @if ($activities->hasPages())
                <div>{{ $activities->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
