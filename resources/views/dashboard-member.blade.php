@php
    use Illuminate\Support\Str;

    $me = auth()->user();
    $hour = (int) now()->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
    $firstName = Str::of($me->name)->explode(' ')->first();

    $a = $analytics;
    $period = $a['period'];
    $kpi = $a['kpis'];
    $versus = 'this point '.$period->previousLabel();
    $periodNoun = $period->key;

    // Status colors as on the planning donut, except Recheck: orange-700 so it
    // stays distinguishable from In review's amber (validated for CVD).
    $statusFill = [
        'todo' => '#94a3b8', 'in_progress' => '#3b82f6', 'in_review' => '#f59e0b',
        'recheck' => '#c2410c', 'done' => '#10b981',
    ];
    $donePct = $a['taskTotal'] > 0 ? (int) round($a['statusCounts']['done'] / $a['taskTotal'] * 100) : 0;

    // One encouraging headline — progress is celebrated, a dip is never scolded.
    $completed = $kpi['completed']['current'];
    $change = $kpi['completed']['change'];
    $tasksWord = fn (int $n) => $n.' '.Str::plural('task', $n);
    if (! $a['hasHistory']) {
        $headline = 'Welcome aboard, '.$firstName.'!';
        $subline = 'This page grows with you: move a task to Done or fill in your tasksheet and your stats start here.';
    } elseif ($completed === 0) {
        $headline = 'A fresh '.$periodNoun.' — let\'s get it going.';
        $subline = $a['focus'] ? 'Your focus list below is a good place to start.' : 'Pick something from the board and your first win will show up here.';
    } elseif ($change > 0) {
        $headline = 'You\'ve finished '.$tasksWord($completed).' this '.$periodNoun.' — '.$change.' more than at '.$versus.'.';
        $subline = 'Great momentum. Keep it rolling!';
    } elseif ($change === 0) {
        $headline = $tasksWord($completed).' done this '.$periodNoun.' — right on your usual rhythm.';
        $subline = 'Steady and consistent. Nice work.';
    } else {
        $headline = $tasksWord($completed).' done so far this '.$periodNoun.'.';
        $subline = 'Every finished task counts. Keep going!';
    }

    $pace = $a['pace'];
    $ringPct = $pace['pct'] ?? 0;
    $streak = $a['streak'];
    $best = $a['bestWeek'];

    $fmtDays = fn (?float $d) => $d === null ? '—' : rtrim(rtrim(number_format($d, 1), '0'), '.').'d';
    $meter = fn (?int $pct) => $pct === null ? 0 : max($pct, $pct > 0 ? 3 : 0);
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header title="My day" :description="$greeting.', '.$firstName.' · '.today()->format('l, F j')">
            <x-slot name="actions">
                <form method="GET" action="{{ route('dashboard') }}" x-data="filterBar" @change="onChange($event)">
                    <fieldset class="segmented">
                        <legend class="sr-only">Analytics period</legend>
                        @foreach (array_keys(\App\Services\MemberPeriod::PERIODS) as $key)
                            <label><input type="radio" name="period" value="{{ $key }}" @checked($period->key === $key)>{{ ucfirst($key) }}</label>
                        @endforeach
                    </fieldset>
                    <button type="submit" class="sr-only">Apply period</button>
                </form>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container space-y-6">
            @include('partials.onboarding-checklist')

            {{-- ============ MOMENTUM ============ --}}
            <section class="card overflow-hidden bg-gradient-to-br from-brand-50 via-white to-white" aria-labelledby="momentum-title">
                <div class="flex flex-col gap-6 p-5 sm:p-6 md:flex-row md:items-center">
                    <div class="min-w-0 flex-1">
                        <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-brand-700">
                            <x-icon name="sparkles" variant="outline" class="h-4 w-4" /> {{ $period->label() }}
                        </p>
                        <h2 id="momentum-title" class="mt-2 text-lg font-semibold text-slate-900 sm:text-xl">{{ $headline }}</h2>
                        <p class="mt-1 text-sm text-slate-600">{{ $subline }}</p>

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            @if ($streak['current'] > 0)
                                <span class="badge badge-warning"><span aria-hidden="true">🔥</span> {{ $streak['current'] }}-day streak</span>
                            @elseif ($a['hasHistory'])
                                <span class="badge badge-slate">Finish a task or log your tasksheet to start a streak</span>
                            @endif
                            @if ($best['count'] > 0)
                                <span class="badge badge-violet"><span aria-hidden="true">🏆</span> Best week: {{ $tasksWord($best['count']) }}</span>
                            @endif
                            @if ($streak['activeToday'])
                                <span class="badge badge-success">Active today ✓</span>
                            @endif
                        </div>
                    </div>

                    {{-- Weekly pace ring: this week against the member's own 8-week average --}}
                    <div class="flex flex-none items-center gap-4 md:border-l md:border-slate-100 md:pl-6">
                        <div class="relative h-24 w-24">
                            <svg viewBox="0 0 42 42" class="h-full w-full -rotate-90" role="img"
                                 aria-label="{{ $pace['goal'] ? $pace['thisWeek'].' of '.$pace['goal'].' tasks toward your usual weekly pace' : $pace['thisWeek'].' tasks completed this week' }}">
                                <circle cx="21" cy="21" r="15.9155" fill="none" stroke="#e0e7ff" stroke-width="4" />
                                @if ($ringPct > 0)
                                    <circle cx="21" cy="21" r="15.9155" fill="none" stroke-width="4" stroke-linecap="round"
                                            stroke="{{ $ringPct >= 100 ? '#10b981' : '#0b63f6' }}"
                                            stroke-dasharray="{{ $ringPct }} {{ 100 - $ringPct }}" />
                                @endif
                            </svg>
                            <div class="absolute inset-0 flex flex-col items-center justify-center">
                                <span class="tabular text-xl font-semibold leading-none text-slate-900">{{ $pace['thisWeek'] }}@if ($pace['goal'])<span class="text-sm font-medium text-slate-400">/{{ $pace['goal'] }}</span>@endif</span>
                                <span class="mt-1 text-[10px] text-slate-500">this week</span>
                            </div>
                        </div>
                        <div class="max-w-[11rem] text-sm">
                            @if ($pace['goal'] === null)
                                <p class="font-medium text-slate-900">Weekly pace</p>
                                <p class="mt-0.5 text-xs text-slate-500">Complete a few tasks and we'll learn your usual weekly rhythm.</p>
                            @elseif ($ringPct >= 100)
                                <p class="font-medium text-success-700">Pace reached <span aria-hidden="true">🎉</span></p>
                                <p class="mt-0.5 text-xs text-slate-500">You've matched your usual {{ $pace['goal'] }} a week. Anything more is a bonus.</p>
                            @else
                                <p class="font-medium text-slate-900">{{ $pace['goal'] - $pace['thisWeek'] }} to your usual pace</p>
                                <p class="mt-0.5 text-xs text-slate-500">Based on your average over the last {{ \App\Services\MemberAnalyticsService::PACE_WEEKS }} weeks.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </section>

            {{-- ============ TODAY ============ --}}
            <div class="stat-strip">
                <a href="{{ route('board.index') }}" class="stat-cell hover:bg-slate-50">
                    <p class="stat-label"><x-icon name="view-columns" variant="outline" class="h-4 w-4 max-sm:hidden text-sky-600" /><span class="truncate">Open tasks</span></p>
                    <p class="stat-value">{{ $tasks->count() }}</p>
                    <p class="stat-sub hidden sm:block">assigned to you</p>
                </a>
                <div @class(['stat-cell', 'bg-danger-50/50' => $overdueCount > 0])>
                    <p @class(['stat-label', 'text-danger-700' => $overdueCount > 0])>
                        <x-icon name="exclamation-circle" variant="outline" @class(['h-4 w-4 max-sm:hidden', 'text-danger-600' => $overdueCount > 0, 'text-slate-400' => $overdueCount === 0]) />
                        <span class="truncate">Overdue</span>
                    </p>
                    <p @class(['stat-value', 'text-danger-700' => $overdueCount > 0])>{{ $overdueCount }}</p>
                    <p @class(['stat-sub hidden sm:block', 'text-danger-700' => $overdueCount > 0])>{{ $overdueCount > 0 ? 'past their due date' : 'nothing late' }}</p>
                </div>
                <x-stat label="Due soon" :value="$a['today']['dueSoon']" icon="clock" tone="amber"
                        :sub="'in the next '.\App\Services\MemberAnalyticsService::DUE_SOON_DAYS.' days'" />
                <x-stat label="In review" :value="$a['today']['inReview']" icon="magnifying-glass" tone="violet"
                        :sub="$a['today']['inProgress'].' in progress'" />
            </div>

            <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">

                {{-- ============ FOCUS TODAY ============ --}}
                <section class="card lg:col-span-2" aria-labelledby="focus-title">
                    <div class="card-header">
                        <div>
                            <h2 id="focus-title" class="card-title">Focus today</h2>
                            <p class="card-subtitle">Overdue first, then due today, then what's in progress</p>
                        </div>
                    </div>
                    @if (empty($a['focus']))
                        <x-empty-state icon="sparkles" title="Nothing urgent right now."
                                       description="A great moment to pick up something new from the board.">
                            <a href="{{ route('board.index') }}" class="font-medium text-brand-700 hover:text-brand-800">Open the board →</a>
                        </x-empty-state>
                    @else
                        <ol class="divide-y divide-slate-100">
                            @foreach ($a['focus'] as $item)
                                @php $task = $item['task']; @endphp
                                <li>
                                    <a href="{{ route('tasks.show', $task) }}" class="group flex items-center gap-3 px-5 py-3 transition-colors hover:bg-slate-50/80 sm:px-6">
                                        <span @class([
                                            'h-2 w-2 flex-none rounded-full',
                                            'bg-danger-500' => $item['reason'] === 'overdue',
                                            'bg-warning-500' => $item['reason'] === 'due_today',
                                            'bg-blue-500' => $item['reason'] === 'in_progress',
                                        ]) aria-hidden="true"></span>
                                        <span class="min-w-0 flex-1 truncate text-sm font-medium text-slate-900 group-hover:text-brand-700">{{ $task->title }}</span>
                                        @if ($item['reason'] === 'overdue')
                                            <span class="badge badge-danger flex-none">Was due {{ $task->due_date->format('M j') }}</span>
                                        @elseif ($item['reason'] === 'due_today')
                                            <span class="badge badge-warning flex-none">Due today</span>
                                        @else
                                            <span class="badge badge-brand flex-none">In progress</span>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </section>

                {{-- Today's tasksheet --}}
                <section class="card" aria-labelledby="sheet-title">
                    <div class="card-header">
                        <h2 id="sheet-title" class="card-title">Today's tasksheet</h2>
                        <a href="{{ route('tasksheet.user', $me) }}" class="inline-flex items-center gap-1 text-sm font-medium text-brand-700 hover:text-brand-800">
                            History <x-icon name="arrow-right" variant="outline" class="h-4 w-4" />
                        </a>
                    </div>
                    @if ($teams->isEmpty())
                        <p class="px-5 py-4 text-sm text-slate-500 sm:px-6">You're not on a team yet.</p>
                    @else
                        <ul class="divide-y divide-slate-100">
                            @foreach ($teams as $team)
                                @php
                                    $entry = $sheetEntries->get($team->id);
                                    $sheetUrl = route('tasksheet.index', ['team' => $team->id]);
                                @endphp
                                <li class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1.5 px-5 py-3 sm:px-6">
                                    <span class="flex min-w-0 items-center gap-2.5 text-sm text-slate-700">
                                        <span class="h-2.5 w-2.5 flex-none rounded-full" style="background-color: {{ $team->color }}"></span>
                                        <span class="truncate">{{ $team->name }}</span>
                                        @if ($entry?->isHalfDay())
                                            <span class="badge badge-warning">Half day</span>
                                        @endif
                                    </span>
                                    @if ($entry?->isFullDayLeave())
                                        <a href="{{ $sheetUrl }}" class="badge badge-info flex-none hover:brightness-95">{{ $entry->leaveLabel() }}</a>
                                    @elseif ($entry?->isFullyFilled())
                                        <a href="{{ $sheetUrl }}" class="badge badge-success flex-none hover:brightness-95">Filled ✓</a>
                                    @elseif ($entry?->isPartiallyFilled())
                                        <a href="{{ $sheetUrl }}" title="Fill the remaining fields"
                                           class="badge badge-brand flex-none hover:brightness-95">Partially filled · {{ $entry->filledFieldCount() }}/{{ count(\App\Models\TasksheetEntry::TASK_FIELDS) }}</a>
                                    @else
                                        <a href="{{ $sheetUrl }}" class="badge badge-warning flex-none hover:brightness-95">Not filled — fill now</a>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>

            {{-- ============ PERIOD KPIs ============ --}}
            <section aria-labelledby="kpi-title" class="space-y-3">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 id="kpi-title" class="text-sm font-semibold text-slate-900">{{ $period->label() }} at a glance</h2>
                    <p class="text-xs text-slate-500">
                        {{ $period->start->format('M j') }} – {{ $period->end->format('M j') }}, compared with
                        {{ $period->previousStart->format('M j') }} – {{ $period->previousEnd->format('M j') }}
                    </p>
                </div>
                <div class="stat-strip">
                    <x-stat label="Completed" :value="$kpi['completed']['current']" icon="check" tone="emerald"
                            :sub="Str::plural('task', $kpi['completed']['current']).' finished'">
                        <div class="mt-2">@include('partials.delta', ['change' => $kpi['completed']['change'], 'versus' => $versus])</div>
                    </x-stat>
                    <x-stat label="On-time rate" :value="$kpi['onTimeRate']['current'] === null ? '—' : $kpi['onTimeRate']['current'].'%'" icon="calendar" tone="sky"
                            :sub="$kpi['onTimeRate']['current'] === null ? 'No dated tasks finished yet' : 'finished by their due date'">
                        <div class="mt-2">@include('partials.delta', ['change' => $kpi['onTimeRate']['change'], 'unit' => ' pts', 'versus' => $versus])</div>
                    </x-stat>
                    <x-stat label="Avg cycle time" :value="$fmtDays($kpi['cycleDays']['current'])" icon="clock" tone="violet"
                            :sub="$kpi['cycleDays']['current'] === null ? 'Starts once a task goes In Progress → Done' : 'In Progress → Done'">
                        <div class="mt-2">@include('partials.delta', ['change' => $kpi['cycleDays']['change'], 'unit' => 'd', 'versus' => $versus, 'lowerIsBetter' => true])</div>
                    </x-stat>
                    <x-stat label="Tickets logged" :value="$kpi['tickets']['current']" icon="clipboard-check" tone="teal"
                            :sub="$kpi['ticketPoints']['current'].' ticket pts · '.$kpi['workPoints']['current'].' work pts'">
                        <div class="mt-2">@include('partials.delta', ['change' => $kpi['tickets']['change'], 'versus' => $versus])</div>
                    </x-stat>
                </div>
            </section>

            {{-- ============ TRENDS ============ --}}
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <section class="card card-pad lg:col-span-2" aria-labelledby="trend-title">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <h2 id="trend-title" class="card-title">Completed per week</h2>
                            <p class="card-subtitle">Last {{ count($a['weekly']) }} weeks · your best week is highlighted</p>
                        </div>
                        @if ($best['count'] > 0)
                            <p class="text-right text-xs text-slate-500">
                                <span aria-hidden="true">🏆</span> Personal best
                                <span class="block text-sm font-semibold text-slate-900">{{ $tasksWord($best['count']) }}</span>
                                week of {{ $best['weekStart']->format('M j') }}
                            </p>
                        @endif
                    </div>
                    <div class="mt-5">
                        @if (collect($a['weekly'])->sum('count') === 0)
                            <div class="flex h-40 flex-col items-center justify-center rounded-lg border border-dashed border-slate-200 text-center">
                                <p class="text-sm font-medium text-slate-700">Your weekly trend starts with your first finished task.</p>
                                <p class="mt-1 text-xs text-slate-500">Move a task to Done and watch this chart grow.</p>
                            </div>
                        @else
                            @include('partials.column-chart', [
                                'label' => 'Tasks completed per week, last '.count($a['weekly']).' weeks',
                                'bars' => collect($a['weekly'])->map(fn ($w) => [
                                    'label' => $w['weekStart']->format('M j'),
                                    'value' => $w['count'],
                                    'current' => $w['current'],
                                    'highlight' => $w['best'],
                                    'title' => $tasksWord($w['count']).' · week of '.$w['weekStart']->format('M j').($w['best'] ? ' · best week' : '').($w['current'] ? ' · this week' : ''),
                                ])->all(),
                            ])
                        @endif
                    </div>
                </section>

                <section class="card card-pad" aria-labelledby="status-title">
                    <h2 id="status-title" class="card-title">My tasks by status</h2>
                    <p class="card-subtitle">Everything assigned to you (archived work isn't counted)</p>
                    <div class="mt-5">
                        @if ($a['taskTotal'] === 0)
                            <div class="flex flex-col items-center justify-center py-6 text-center">
                                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-50 text-slate-400 ring-1 ring-inset ring-slate-200">
                                    <x-icon name="clipboard-check" variant="outline" class="h-5 w-5" />
                                </span>
                                <p class="mt-3 max-w-[16rem] text-sm text-slate-500">No tasks assigned yet. They'll appear here as soon as you pick one up.</p>
                            </div>
                        @else
                            @include('partials.donut', [
                                'label' => 'My tasks by status, '.$donePct.'% done',
                                'center' => $donePct.'%',
                                'caption' => 'done',
                                'segments' => collect($a['statusCounts'])->map(fn ($count, $status) => [
                                    'label' => \App\Models\Task::STATUSES[$status], 'value' => $count, 'color' => $statusFill[$status],
                                ])->values()->all(),
                            ])
                            <p class="mt-4 border-t border-slate-100 pt-3 text-xs text-slate-500">
                                {{ $tasksWord($a['taskTotal']) }} in total
                            </p>
                        @endif
                    </div>
                </section>
            </div>

            {{-- ============ ACTIVITY + HABITS ============ --}}
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <section class="card card-pad min-w-0 lg:col-span-2" aria-labelledby="heatmap-title">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <h2 id="heatmap-title" class="card-title">Your activity</h2>
                            <p class="card-subtitle">Tasks completed, comments and tasksheet days · last {{ \App\Services\MemberAnalyticsService::HEATMAP_WEEKS }} weeks</p>
                        </div>
                        <p class="text-xs text-slate-500">
                            <span class="tabular font-semibold text-slate-900">{{ $a['heatmap']['total'] }}</span> contributions on
                            <span class="tabular font-semibold text-slate-900">{{ $a['heatmap']['activeDays'] }}</span> {{ Str::plural('day', $a['heatmap']['activeDays']) }}
                        </p>
                    </div>
                    <div class="mt-4">
                        @include('partials.contribution-heatmap', ['weeks' => $a['heatmap']['weeks']])
                    </div>
                    @if ($a['heatmap']['total'] === 0)
                        <p class="mt-2 text-xs text-slate-500">Quiet so far. Each task you finish, comment you write and tasksheet you log lights up a square.</p>
                    @endif
                </section>

                <section class="card card-pad" aria-labelledby="habits-title">
                    <h2 id="habits-title" class="card-title">Habits</h2>
                    <p class="card-subtitle">{{ $period->label() }}</p>

                    <div class="mt-4 flex items-center gap-3">
                        <span class="flex h-11 w-11 flex-none items-center justify-center rounded-xl bg-warning-50 text-xl ring-1 ring-inset ring-warning-600/15" aria-hidden="true">🔥</span>
                        <div>
                            <p class="tabular text-lg font-semibold leading-none text-slate-900">{{ $streak['current'] }} {{ Str::plural('day', $streak['current']) }}</p>
                            <p class="mt-1 text-xs text-slate-500">
                                Current streak · best {{ $streak['best'] }} {{ Str::plural('day', $streak['best']) }}
                            </p>
                        </div>
                    </div>
                    <p class="mt-1.5 text-[11px] text-slate-400">Weekdays with a finished task or a complete tasksheet. Weekends and leave never break it.</p>

                    <dl class="mt-5 space-y-4">
                        <div>
                            <div class="flex items-baseline justify-between text-sm">
                                <dt class="text-slate-600">Standup attendance</dt>
                                <dd class="tabular font-semibold text-slate-900">{{ $kpi['standupRate']['current'] === null ? '—' : $kpi['standupRate']['current'].'%' }}</dd>
                            </div>
                            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100" role="img"
                                 aria-label="Standup attendance {{ $kpi['standupRate']['current'] === null ? 'not recorded yet' : $kpi['standupRate']['current'].'%' }}">
                                <div class="h-full rounded-full bg-brand-500" style="width: {{ $meter($kpi['standupRate']['current']) }}%"></div>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ $kpi['standupMarked']['current'] === 0 ? 'No standups recorded yet' : $kpi['standupAttended']['current'].' of '.$kpi['standupMarked']['current'].' recorded standups' }}
                            </p>
                        </div>
                        <div>
                            <div class="flex items-baseline justify-between text-sm">
                                <dt class="text-slate-600">Tasksheet days logged</dt>
                                <dd class="tabular font-semibold text-slate-900">{{ $kpi['tasksheetRate']['current'] === null ? '—' : $kpi['tasksheetRate']['current'].'%' }}</dd>
                            </div>
                            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100" role="img"
                                 aria-label="Tasksheet days logged {{ $kpi['tasksheetRate']['current'] === null ? 'not applicable yet' : $kpi['tasksheetRate']['current'].'%' }}">
                                <div class="h-full rounded-full bg-emerald-500" style="width: {{ $meter($kpi['tasksheetRate']['current']) }}%"></div>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">
                                @if (! $a['onTeam'])
                                    Join a team to start your tasksheet
                                @elseif ($kpi['daysExpected']['current'] === 0)
                                    Nothing to log yet this {{ $periodNoun }}
                                @else
                                    {{ $kpi['daysLogged']['current'] }} of {{ $kpi['daysExpected']['current'] }} working {{ Str::plural('day', $kpi['daysExpected']['current']) }} complete
                                @endif
                            </p>
                        </div>
                        <div>
                            <div class="flex items-baseline justify-between text-sm">
                                <dt class="text-slate-600">Lead time</dt>
                                <dd class="tabular font-semibold text-slate-900">{{ $fmtDays($kpi['leadDays']['current']) }}</dd>
                            </div>
                            @php
                                $leadPoints = collect($a['leadTimeTrend'])->map(fn ($w) => [
                                    'label' => $w['weekStart']->format('M j'), 'value' => $w['days'],
                                    'title' => 'Week of '.$w['weekStart']->format('M j').': '.$fmtDays($w['days']).' from created to done',
                                ])->all();
                                $leadMax = max(collect($a['leadTimeTrend'])->max('days') ?? 0, 1);
                            @endphp
                            @include('partials.spark-line', [
                                'points' => $leadPoints, 'max' => $leadMax * 1.15, 'color' => '#0b63f6', 'guide' => false,
                                'label' => 'Average lead time per week, last '.count($leadPoints).' weeks',
                            ])
                            <p class="text-xs text-slate-500">Average days from created to done, per week</p>
                        </div>
                    </dl>

                    <p class="mt-5 border-t border-slate-100 pt-3 text-xs text-slate-500">
                        {{ $period->label() }}: {{ $kpi['meetings']['current'] }} {{ Str::plural('meeting', $kpi['meetings']['current']) }} attended ·
                        {{ $kpi['comments']['current'] }} {{ Str::plural('comment', $kpi['comments']['current']) }}
                    </p>
                </section>
            </div>

            <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">

                {{-- ============ MY TASKS ============ --}}
                <section id="my-tasks" class="card lg:col-span-2" aria-labelledby="my-tasks-title">
                    <div class="card-header">
                        <div class="flex items-center gap-2">
                            <h2 id="my-tasks-title" class="card-title">My tasks</h2>
                            <span class="badge badge-slate">{{ $tasks->count() }}</span>
                        </div>
                        <a href="{{ route('board.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-brand-700 hover:text-brand-800">
                            Open board <x-icon name="arrow-right" variant="outline" class="h-4 w-4" />
                        </a>
                    </div>

                    @if ($tasks->isEmpty())
                        <x-empty-state icon="check" title="You're all caught up." description="No open tasks assigned to you." />
                    @else
                        <ul class="divide-y divide-slate-100">
                            @foreach ($tasks as $task)
                                @php
                                    $overdue = $task->due_date && $task->due_date->isPast() && ! $task->due_date->isToday();
                                    $dueToday = $task->due_date?->isToday();
                                @endphp
                                <li>
                                    <a href="{{ route('tasks.show', $task) }}" class="group flex flex-col gap-2 px-5 py-3.5 transition-colors hover:bg-slate-50/80 sm:flex-row sm:items-center sm:gap-4 sm:px-6">
                                        <div class="w-full min-w-0 flex-1">
                                            <p class="truncate text-sm font-medium text-slate-900 group-hover:text-brand-700">{{ $task->title }}</p>
                                            @if ($task->release)
                                                <p class="mt-1 flex items-center gap-1.5 text-xs text-slate-500">
                                                    <x-icon name="rocket" variant="outline" class="h-3.5 w-3.5 text-violet-500" />
                                                    <span class="truncate">{{ $task->release->name }}</span>
                                                </p>
                                            @endif
                                        </div>
                                        <div class="flex flex-none flex-wrap items-center gap-2">
                                            @if ($task->due_date)
                                                <span @class([
                                                    'badge',
                                                    'badge-danger' => $overdue,
                                                    'badge-warning' => $dueToday,
                                                    'badge-slate' => ! $overdue && ! $dueToday,
                                                ])>
                                                    {{ $overdue ? 'Overdue · ' : ($dueToday ? 'Due today · ' : 'Due ') }}{{ $task->due_date->format('M j') }}
                                                </span>
                                            @endif
                                            @include('partials.status-badge', ['status' => $task->status])
                                        </div>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                {{-- ============ SIDEBAR ============ --}}
                <div class="space-y-6">

                    {{-- Recently completed --}}
                    <section id="recently-completed" class="card" aria-labelledby="recent-title">
                        <div class="card-header">
                            <h2 id="recent-title" class="card-title">Recently completed</h2>
                            @if ($a['recent'])
                                <span class="text-xs font-medium text-success-700">Nice work <span aria-hidden="true">🎉</span></span>
                            @endif
                        </div>
                        @if (empty($a['recent']))
                            <p class="px-5 py-4 text-sm text-slate-500 sm:px-6">Your finished work will show up here. The first one is always the best feeling.</p>
                        @else
                            <ul class="divide-y divide-slate-100">
                                @foreach ($a['recent'] as $item)
                                    <li>
                                        <a href="{{ route('tasks.show', $item['task']) }}" class="group flex items-center gap-3 px-5 py-3 transition-colors hover:bg-slate-50/80 sm:px-6">
                                            <span class="flex h-6 w-6 flex-none items-center justify-center rounded-full bg-success-50 text-success-600 ring-1 ring-inset ring-success-600/20">
                                                <x-icon name="check" variant="outline" class="h-3.5 w-3.5" />
                                            </span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block truncate text-sm font-medium text-slate-900 group-hover:text-brand-700">{{ $item['task']->title }}</span>
                                                <span class="mt-0.5 block text-xs text-slate-500">
                                                    @if ($item['completedAt']->isToday())
                                                        <span class="font-medium text-success-700">Done today</span>
                                                    @else
                                                        Done {{ $item['completedAt']->diffForHumans() }}
                                                    @endif
                                                </span>
                                            </span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>

                    {{-- Upcoming meetings --}}
                    <section class="card" aria-labelledby="meetings-title">
                        <div class="card-header">
                            <div class="flex items-center gap-2">
                                <h2 id="meetings-title" class="card-title">Upcoming meetings</h2>
                                <span class="badge badge-slate" title="In the next two weeks">{{ $meetings->count() }}</span>
                            </div>
                            <a href="{{ route('calendar.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-brand-700 hover:text-brand-800">
                                Calendar <x-icon name="arrow-right" variant="outline" class="h-4 w-4" />
                            </a>
                        </div>
                        @if ($meetings->isEmpty())
                            <p class="px-5 py-4 text-sm text-slate-500 sm:px-6">No meetings in the next two weeks.</p>
                        @else
                            <ul class="divide-y divide-slate-100">
                                @foreach ($meetings as $meeting)
                                    @php $isToday = $meeting->starts_at->isToday(); @endphp
                                    <li>
                                        <a href="{{ route('events.show', $meeting) }}" class="group flex items-center gap-3.5 px-5 py-3 transition-colors hover:bg-slate-50/80 sm:px-6">
                                            <span @class([
                                                'flex h-11 w-11 flex-none flex-col items-center justify-center rounded-lg ring-1 ring-inset',
                                                'bg-brand-50 text-brand-700 ring-brand-600/15' => $isToday,
                                                'bg-white text-slate-700 ring-slate-200' => ! $isToday,
                                            ])>
                                                <span class="text-[9px] font-semibold uppercase leading-none tracking-wide">{{ $meeting->starts_at->format('M') }}</span>
                                                <span class="tabular mt-0.5 text-base font-semibold leading-none">{{ $meeting->starts_at->format('j') }}</span>
                                            </span>
                                            <span class="min-w-0">
                                                <span class="block truncate text-sm font-medium text-slate-900 group-hover:text-brand-700">{{ $meeting->title }}</span>
                                                <span class="mt-0.5 block text-xs text-slate-500">
                                                    {{ $isToday ? 'Today' : $meeting->starts_at->format('l') }} · {{ $meeting->timeLabel() }}
                                                </span>
                                            </span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
