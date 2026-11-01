{{--
    Role-tailored delivery health for the planning dashboard (DeliveryAnalyticsService).
    @include('partials.delivery-health', ['d' => $delivery])
    Viewers get the portfolio sections only: `people`, `attention` and
    `compliance` are null for them and their cards are not rendered.
--}}
@php
    use App\Services\DeliveryAnalyticsService as Delivery;
    use Illuminate\Support\Str;

    $k = $d['kpis'];
    $b = $d['backlog'];
    $versus = 'prior '.$d['window']['days'].' days';
    $fmtDays = fn (?float $v) => $v === null ? '—' : rtrim(rtrim(number_format($v, 1), '0'), '.').'d';
    $fmtPct = fn (?int $v) => $v === null ? '—' : $v.'%';
    $best = collect($d['weekly'])->firstWhere('best', true);
    $agingMax = max(collect($d['aging'])->max('count'), 1);
    $audienceLabel = ['org' => 'All teams view', 'team' => 'Your teams', 'viewer' => 'Portfolio view'][$d['audience']];
@endphp

<section aria-labelledby="health-title" class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3 border-t border-slate-200 pt-6">
        <div>
            <h2 id="health-title" class="text-base font-semibold text-slate-900">Delivery health</h2>
            <p class="mt-0.5 text-sm text-slate-500">
                {{ $d['scopeLabel'] }} · last {{ $d['window']['days'] }} days
                ({{ $d['window']['start']->format('M j') }} – {{ $d['window']['end']->format('M j') }}) against the {{ $d['window']['days'] }} before
            </p>
        </div>
        <span class="badge badge-brand">{{ $audienceLabel }}</span>
    </div>

    {{-- Flow KPIs --}}
    <div class="stat-strip">
        <x-stat label="Throughput" :value="$k['throughput']['current']" icon="check" tone="emerald" sub="tasks moved to Done">
            <div class="mt-2">@include('partials.delta', ['change' => $k['throughput']['change'], 'versus' => $versus])</div>
        </x-stat>
        <x-stat label="Avg cycle time" :value="$fmtDays($k['cycleDays']['current'])" icon="clock" tone="violet"
                :sub="$k['cycleDays']['current'] === null ? 'No In Progress → Done moves yet' : 'In Progress → Done'">
            <div class="mt-2">@include('partials.delta', ['change' => $k['cycleDays']['change'], 'unit' => 'd', 'versus' => $versus, 'lowerIsBetter' => true])</div>
        </x-stat>
        <x-stat label="On-time rate" :value="$fmtPct($k['onTimeRate']['current'])" icon="calendar" tone="sky"
                :sub="$k['onTimeRate']['current'] === null ? 'No dated tasks finished' : 'finished by their due date'">
            <div class="mt-2">@include('partials.delta', ['change' => $k['onTimeRate']['change'], 'unit' => ' pts', 'versus' => $versus])</div>
        </x-stat>
        <x-stat label="Rework rate" :value="$fmtPct($k['reworkRate']['current'])" icon="arrow-left" tone="amber"
                :sub="$k['reworkRate']['current'] === null ? 'Nothing finished yet' : 'of finished work went through Recheck'">
            <div class="mt-2">@include('partials.delta', ['change' => $k['reworkRate']['change'], 'unit' => ' pts', 'versus' => $versus, 'lowerIsBetter' => true])</div>
        </x-stat>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Throughput trend --}}
        <section class="card card-pad lg:col-span-2" aria-labelledby="throughput-title">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h3 id="throughput-title" class="card-title">Throughput per week</h3>
                    <p class="card-subtitle">Tasks completed, last {{ count($d['weekly']) }} weeks · best week highlighted</p>
                </div>
                @if ($best)
                    <p class="text-right text-xs text-slate-500">Best week
                        <span class="block text-sm font-semibold text-slate-900">{{ $best['count'] }} {{ Str::plural('task', $best['count']) }}</span>
                        week of {{ $best['weekStart']->format('M j') }}
                    </p>
                @endif
            </div>
            <div class="mt-5">
                @if (! $best)
                    <div class="flex h-40 items-center justify-center rounded-lg border border-dashed border-slate-200 text-sm text-slate-500">
                        No tasks completed in the last {{ count($d['weekly']) }} weeks.
                    </div>
                @else
                    @include('partials.column-chart', [
                        'label' => 'Tasks completed per week, last '.count($d['weekly']).' weeks',
                        'bars' => collect($d['weekly'])->map(fn ($w) => [
                            'label' => $w['weekStart']->format('M j'),
                            'value' => $w['count'],
                            'current' => $w['current'],
                            'highlight' => $w['best'],
                            'title' => $w['count'].' '.Str::plural('task', $w['count']).' · week of '.$w['weekStart']->format('M j'),
                        ])->all(),
                    ])
                @endif
            </div>
        </section>

        {{-- Open work + aging --}}
        <section class="card card-pad" aria-labelledby="backlog-title">
            <h3 id="backlog-title" class="card-title">Open work</h3>
            <p class="card-subtitle">Not yet done · archived work isn't counted</p>
            <dl class="mt-4 grid grid-cols-3 gap-3 text-center">
                @foreach ([
                    ['Open', $b['open'], false],
                    ['Overdue', $b['overdue'], $b['overdue'] > 0],
                    ['Due soon', $b['dueSoon'], false],
                    ['In review', $b['inReview'], false],
                    ['Recheck', $b['recheck'], false],
                    ['Unassigned', $b['unassigned'], false],
                ] as [$label, $value, $warn])
                    <div @class(['rounded-lg px-2 py-2.5 ring-1 ring-inset', 'bg-warning-50 ring-warning-600/20' => $warn, 'bg-slate-50 ring-slate-200' => ! $warn])>
                        <dd @class(['tabular text-lg font-semibold leading-none', 'text-warning-700' => $warn, 'text-slate-900' => ! $warn])>{{ $value }}</dd>
                        <dt @class(['mt-1 text-[11px]', 'text-warning-800' => $warn, 'text-slate-500' => ! $warn])>{{ $label }}</dt>
                    </div>
                @endforeach
            </dl>

            <h4 class="mt-5 text-xs font-semibold uppercase tracking-wide text-slate-500">Age of open work</h4>
            <ul class="mt-2 space-y-2.5" role="img"
                aria-label="Open tasks by age: {{ collect($d['aging'])->map(fn ($a) => $a['label'].' '.$a['count'])->implode(', ') }}">
                @foreach ($d['aging'] as $i => $bucket)
                    <li aria-hidden="true">
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="text-slate-600">{{ $bucket['label'] }}</span>
                            <span class="tabular font-semibold text-slate-900">{{ $bucket['count'] }}</span>
                        </div>
                        <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-100">
                            <div @class(['h-full rounded-full', ['bg-brand-300', 'bg-brand-400', 'bg-brand-600', 'bg-brand-800'][$i] ?? 'bg-brand-600'])
                                 style="width: {{ $bucket['count'] > 0 ? max(4, round($bucket['count'] / $agingMax * 100)) : 0 }}%"></div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Release health --}}
        <section class="card lg:col-span-2" aria-labelledby="release-health-title">
            <div class="card-header">
                <div>
                    <h3 id="release-health-title" class="card-title">Release progress vs plan</h3>
                    <p class="card-subtitle">Ongoing releases in this view · tasks done against time used</p>
                </div>
                @if ($d['atRiskCount'] > 0)
                    <span class="badge badge-warning"><x-icon name="exclamation-circle" variant="outline" class="h-3.5 w-3.5" />{{ $d['atRiskCount'] }} at risk</span>
                @elseif ($d['releases'])
                    <span class="badge badge-success">All on track</span>
                @endif
            </div>
            @if (empty($d['releases']))
                <p class="px-5 py-6 text-sm text-slate-500 sm:px-6">No ongoing releases in this view.</p>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach (array_slice($d['releases'], 0, 8) as $r)
                        @php $release = $r['release']; @endphp
                        <li class="px-5 py-3.5 sm:px-6">
                            <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                                <a href="{{ route('releases.show', $release) }}" class="flex min-w-0 items-center gap-2 text-sm font-medium text-slate-900 hover:text-brand-700">
                                    <span class="h-2.5 w-2.5 flex-none rounded-full" style="background-color: {{ $release->team?->color ?? '#94a3b8' }}"></span>
                                    <span class="truncate">{{ $release->name }}</span>
                                </a>
                                <span class="flex flex-wrap items-center gap-1.5">
                                    @forelse ($r['flags'] as $flag)
                                        <span class="badge badge-warning">{{ $flag }}</span>
                                    @empty
                                        <span class="badge badge-success">On track</span>
                                    @endforelse
                                </span>
                            </div>
                            <div class="relative mt-2 h-2 rounded-full bg-slate-100" role="img"
                                 aria-label="{{ $release->name }}: {{ $r['pct'] === null ? 'no tasks' : $r['pct'].'% of tasks done' }}, {{ $r['elapsedPct'] }}% of its time used">
                                <div @class(['absolute inset-y-0 left-0 rounded-full', 'bg-warning-500' => $r['atRisk'], 'bg-success-500' => ! $r['atRisk']])
                                     style="width: {{ $r['pct'] ?? 0 }}%"></div>
                                <div class="absolute -inset-y-1 w-0.5 -translate-x-1/2 rounded-full bg-slate-700" style="left: {{ $r['elapsedPct'] }}%" title="{{ $r['elapsedPct'] }}% of time used"></div>
                            </div>
                            <p class="mt-1.5 text-xs text-slate-500">
                                {{ $r['pct'] === null ? 'No tasks yet' : $r['pct'].'% done · '.$r['done'].'/'.$r['total'].' tasks' }}
                                · {{ $r['elapsedPct'] }}% of time used · ends {{ $release->end_date->format('M j') }}
                            </p>
                        </li>
                    @endforeach
                </ul>
                <p class="border-t border-slate-100 px-5 py-2.5 text-[11px] text-slate-400 sm:px-6">
                    The dark tick marks time used. A release is flagged when its team is double-booked, a phase has ended with open work,
                    it trails time used by {{ Delivery::BEHIND_POINTS }}+ points, or it is past its end date.
                </p>
            @endif
        </section>

        {{-- Open tasks by team --}}
        <section class="card card-pad" aria-labelledby="team-load-title">
            <h3 id="team-load-title" class="card-title">Open tasks by team</h3>
            <p class="card-subtitle">Busiest first · overdue in amber</p>
            @if (empty($d['teamLoad']))
                <p class="mt-6 text-sm text-slate-500">No open tasks in this view.</p>
            @else
                @php $loadMax = max(collect($d['teamLoad'])->max('open'), 1); @endphp
                <ul class="mt-5 space-y-3.5">
                    @foreach (array_slice($d['teamLoad'], 0, 8) as $t)
                        <li title="{{ $t['name'] }}: {{ $t['open'] }} open, {{ $t['overdue'] }} overdue">
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="flex min-w-0 items-center gap-2">
                                    <span class="h-2.5 w-2.5 flex-none rounded-full" style="background-color: {{ $t['color'] }}"></span>
                                    <span class="truncate text-slate-700">{{ $t['name'] }}</span>
                                </span>
                                <span class="tabular flex-none font-semibold text-slate-900">
                                    {{ $t['open'] }}@if ($t['overdue'])<span class="ml-1 text-xs font-medium text-warning-700">· {{ $t['overdue'] }} late</span>@endif
                                </span>
                            </div>
                            {{-- overdue share drawn inside the open bar, so the two never disagree --}}
                            <div class="relative mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100">
                                <div class="absolute inset-y-0 left-0 rounded-full" style="width: {{ max(4, round($t['open'] / $loadMax * 100)) }}%; background-color: {{ $t['color'] }}"></div>
                                @if ($t['overdue'])
                                    <div class="absolute inset-y-0 left-0 rounded-full bg-warning-500 ring-2 ring-white" style="width: {{ max(3, round($t['overdue'] / $loadMax * 100)) }}%"></div>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    @if ($d['people'] !== null)
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            {{-- Workload by person --}}
            <section class="card lg:col-span-2" aria-labelledby="people-title">
                <div class="card-header">
                    <div>
                        <h3 id="people-title" class="card-title">{{ $d['audience'] === 'team' ? 'Team members’ load' : 'Workload by person' }}</h3>
                        <p class="card-subtitle">Open tasks per assignee, busiest first ({{ $b['unassigned'] }} unassigned)</p>
                    </div>
                </div>
                @if (empty($d['people']))
                    <p class="px-5 py-6 text-sm text-slate-500 sm:px-6">Nobody has open tasks assigned in this view.</p>
                @else
                    @php $peopleMax = max(collect($d['people'])->max('open'), 1); @endphp
                    <div class="table-wrap">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs text-slate-500">
                                    <th scope="col" class="px-5 py-2 font-medium sm:px-6">Person</th>
                                    <th scope="col" class="w-1/3 px-3 py-2 font-medium">Open</th>
                                    <th scope="col" class="px-3 py-2 text-right font-medium">In progress</th>
                                    <th scope="col" class="px-3 py-2 text-right font-medium">In review</th>
                                    <th scope="col" class="px-5 py-2 text-right font-medium sm:px-6">Overdue</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($d['people'] as $p)
                                    <tr>
                                        <td class="px-5 py-2.5 sm:px-6">
                                            <span class="flex items-center gap-2">
                                                <x-avatar :user="$p['user']" size="sm" />
                                                <span class="truncate text-slate-800">{{ $p['user']->name }}</span>
                                            </span>
                                        </td>
                                        <td class="px-3 py-2.5">
                                            <span class="flex items-center gap-2">
                                                <span class="relative h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                                                    <span class="absolute inset-y-0 left-0 rounded-full bg-brand-500" style="width: {{ max(4, round($p['open'] / $peopleMax * 100)) }}%"></span>
                                                </span>
                                                <span class="tabular w-6 text-right font-semibold text-slate-900">{{ $p['open'] }}</span>
                                            </span>
                                        </td>
                                        <td class="tabular px-3 py-2.5 text-right text-slate-700">{{ $p['inProgress'] }}</td>
                                        <td class="tabular px-3 py-2.5 text-right text-slate-700">{{ $p['inReview'] }}</td>
                                        <td class="px-5 py-2.5 text-right sm:px-6">
                                            @if ($p['overdue'] > 0)
                                                <span class="badge badge-warning tabular">{{ $p['overdue'] }}</span>
                                            @else
                                                <span class="tabular text-slate-400">0</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            {{-- Tasksheet + standup compliance --}}
            @php $c = $d['compliance']; @endphp
            <section class="card card-pad" aria-labelledby="compliance-title">
                <h3 id="compliance-title" class="card-title">Tasksheet & standups</h3>
                <p class="card-subtitle">Last {{ $c['days'] }} working days · {{ $c['members'] }} {{ Str::plural('member', $c['members']) }}</p>

                @if ($c['members'] === 0)
                    <p class="mt-4 text-sm text-slate-500">No team members in this view yet.</p>
                @else
                    <dl class="mt-4 space-y-4">
                        @foreach ([
                            ['Tasksheets complete', $c['fillRate'], $c['filled'].' of '.$c['expected'].' member-days fully filled', 'bg-success-500'],
                            ['Standup attendance', $c['standupRate'], $c['marked'] ? $c['attended'].' of '.$c['marked'].' recorded standups' : 'No standups recorded', 'bg-brand-500'],
                        ] as [$label, $rate, $note, $bar])
                            <div>
                                <div class="flex items-baseline justify-between text-sm">
                                    <dt class="text-slate-600">{{ $label }}</dt>
                                    <dd class="tabular font-semibold text-slate-900">{{ $fmtPct($rate) }}</dd>
                                </div>
                                <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100" role="img" aria-label="{{ $label }} {{ $fmtPct($rate) }}">
                                    <div class="h-full rounded-full {{ $bar }}" style="width: {{ $rate ?? 0 }}%"></div>
                                </div>
                                <p class="mt-1 text-xs text-slate-500">{{ $note }}</p>
                            </div>
                        @endforeach
                    </dl>

                    <h4 class="mt-5 text-xs font-semibold uppercase tracking-wide text-slate-500">By team · today's standup</h4>
                    <ul class="mt-2 divide-y divide-slate-100">
                        @foreach ($c['teams'] as $t)
                            <li class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 py-2 text-sm">
                                <a href="{{ route('tasksheet.index', ['team' => $t['team']->id]) }}" class="flex min-w-0 items-center gap-2 text-slate-700 hover:text-brand-700">
                                    <span class="h-2.5 w-2.5 flex-none rounded-full" style="background-color: {{ $t['team']->color }}"></span>
                                    <span class="truncate">{{ $t['team']->name }}</span>
                                </a>
                                <span class="flex flex-none items-center gap-2 text-xs">
                                    <span class="tabular text-slate-500" title="Tasksheets complete, last {{ $c['days'] }} working days">{{ $fmtPct($t['fillRate']) }} sheets</span>
                                    <span class="badge badge-slate tabular" title="Attended today's standup / members">{{ $t['todayAttended'] }}/{{ $t['members'] }} today</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        {{-- Needs attention --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            @foreach ([
                ['overdue', 'Overdue work', 'Oldest due date first', 'Nothing overdue in this view.'],
                ['inReview', 'Waiting in review', 'Longest untouched first', 'The review queue is empty.'],
            ] as [$key, $title, $subtitle, $empty])
                <section class="card" aria-labelledby="attention-{{ $key }}">
                    <div class="card-header">
                        <div>
                            <h3 id="attention-{{ $key }}" class="card-title">{{ $title }}</h3>
                            <p class="card-subtitle">{{ $subtitle }}</p>
                        </div>
                        <span class="badge badge-slate">{{ $key === 'overdue' ? $b['overdue'] : $b['inReview'] }}</span>
                    </div>
                    @if ($d['attention'][$key]->isEmpty())
                        <p class="px-5 py-4 text-sm text-slate-500 sm:px-6">{{ $empty }}</p>
                    @else
                        <ul class="divide-y divide-slate-100">
                            @foreach ($d['attention'][$key] as $task)
                                <li>
                                    <a href="{{ route('tasks.show', $task) }}" class="group flex items-center gap-3 px-5 py-2.5 transition-colors hover:bg-slate-50/80 sm:px-6">
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-sm font-medium text-slate-900 group-hover:text-brand-700">{{ $task->title }}</span>
                                            <span class="mt-0.5 block truncate text-xs text-slate-500">
                                                {{ $task->assignee?->name ?? 'Unassigned' }}@if ($task->release) · {{ $task->release->name }}@endif
                                            </span>
                                        </span>
                                        @if ($key === 'overdue')
                                            <span class="badge badge-warning flex-none">Due {{ $task->due_date->format('M j') }}</span>
                                        @else
                                            <span class="flex-none text-xs text-slate-500">{{ $task->updated_at->diffForHumans(short: true) }}</span>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endforeach
        </div>
    @endif
</section>
