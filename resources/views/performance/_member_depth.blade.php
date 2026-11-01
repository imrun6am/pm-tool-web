@use('App\Support\ScoreColor')
{{--
    Deeper member analysis on the lead-only scorecard: strengths and growth
    areas from the member's own history, a per-competency week grid, the
    objective record against the previous window, and lead notes.
    Expects $c (PerformanceAnalytics::memberScorecard), $member, $team.
--}}
@php
    $rec = $c['record'];
    $rw = $c['recordWindow'];
    $fmtPct = fn (?int $v) => $v === null ? '—' : $v.'%';
    $fmtDays = fn (?float $v) => $v === null ? '—' : rtrim(rtrim(number_format($v, 1), '0'), '.').'d';
    $versus = 'previous '.$rw['weeks'].' weeks';
    $weekLabels = array_column($c['overallTrend'], 'label');
@endphp

{{-- Strengths + growth areas --}}
<div class="grid gap-6 md:grid-cols-2">
    @foreach ([
        ['strengths', 'Strengths', 'Competencies averaging '.\App\Services\PerformanceAnalytics::STRENGTH_THRESHOLD.'+ over the last '.count($weekLabels).' weeks', 'success'],
        ['growthAreas', 'Growth areas', 'Lowest of their own averages, below '.\App\Services\PerformanceAnalytics::STRENGTH_THRESHOLD, 'warning'],
    ] as [$key, $title, $subtitle, $tone])
        <section class="card card-pad" aria-labelledby="{{ $key }}-title">
            <h3 id="{{ $key }}-title" class="card-title">{{ $title }}</h3>
            <p class="card-subtitle">{{ $subtitle }}</p>
            @if (empty($c[$key]))
                <p class="mt-5 text-sm text-slate-500">
                    {{ $key === 'strengths' ? 'None stand out yet. More ratings will show what they do best.' : 'Nothing below '.\App\Services\PerformanceAnalytics::STRENGTH_THRESHOLD.' in the window.' }}
                </p>
            @else
                <ul class="mt-4 space-y-3">
                    @foreach ($c[$key] as $item)
                        <li class="flex items-center gap-3">
                            <span @class(['flex h-8 w-8 flex-none items-center justify-center rounded-lg text-xs font-semibold ring-1 ring-inset',
                                'bg-success-50 text-success-700 ring-success-600/20' => $tone === 'success',
                                'bg-warning-50 text-warning-800 ring-warning-600/20' => $tone === 'warning'])>{{ ScoreColor::fmt($item['average']) }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium text-slate-800">{{ $item['competency']->name }}</span>
                                <span class="block text-xs text-slate-500">
                                    {{ $item['competency']->categoryLabel() }} · rated {{ $item['weeksRated'] }} {{ \Illuminate\Support\Str::plural('week', $item['weeksRated']) }}
                                    @if ($item['direction'] !== null && $item['direction'] != 0)
                                        · <span class="{{ $item['direction'] > 0 ? 'text-success-700' : 'text-slate-600' }}">{{ $item['direction'] > 0 ? 'improving' : 'easing' }} {{ $item['direction'] > 0 ? '+' : '−' }}{{ ScoreColor::fmt(abs($item['direction'])) }}</span>
                                    @endif
                                </span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endforeach
</div>

{{-- Per-competency history grid --}}
<section class="card card-pad" aria-labelledby="history-grid-title">
    <h3 id="history-grid-title" class="card-title">Competency history</h3>
    <p class="card-subtitle">Weekly rating per competency (daily ones averaged) · last {{ count($weekLabels) }} weeks</p>
    <div class="mt-4 overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-[11px] text-slate-400">
                    <th scope="col" class="py-1.5 pr-3 text-left font-medium">Competency</th>
                    @foreach ($weekLabels as $label)
                        <th scope="col" class="px-1 py-1.5 text-center font-medium">{{ $label }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($c['competencies'] as $row)
                    <tr>
                        <th scope="row" class="max-w-[12rem] truncate py-1 pr-3 text-left font-normal text-slate-700">{{ $row['competency']->name }}</th>
                        @foreach ($row['trend'] as $i => $value)
                            <td class="px-1 py-1 text-center">
                                <span class="inline-flex h-7 w-10 items-center justify-center rounded-md text-xs font-semibold ring-1 ring-inset {{ $value === null ? 'bg-slate-50 text-slate-300 ring-slate-200' : ScoreColor::pill($value) }}"
                                      title="{{ $row['competency']->name }} · {{ $weekLabels[$i] ?? '' }}: {{ $value === null ? 'not rated' : ScoreColor::fmt($value).' — '.ScoreColor::label($value) }}">{{ ScoreColor::fmt($value) }}</span>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

{{-- Objective record --}}
<section aria-labelledby="member-record-title">
    <div class="mb-3 flex flex-wrap items-center gap-2">
        <h3 id="member-record-title" class="text-sm font-semibold text-slate-900">Objective record · last {{ $rw['weeks'] }} weeks</h3>
        <span class="text-xs text-slate-500">{{ $rw['start']->format('M j') }} – {{ $rw['end']->format('M j') }}, against the {{ $rw['weeks'] }} weeks before</span>
        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-slate-400">Context — not part of the score</span>
    </div>
    <div class="stat-strip lg:grid-cols-3 xl:grid-cols-6">
        <x-stat label="Tasksheet" :value="$fmtPct($rec['tasksheetRate']['current'])" icon="clipboard-check" tone="emerald"
                :sub="$rec['daysLogged']['current'].' of '.$rec['daysExpected']['current'].' days complete'">
            <div class="mt-2">@include('partials.delta', ['change' => $rec['tasksheetRate']['change'], 'unit' => ' pts', 'versus' => $versus])</div>
        </x-stat>
        <x-stat label="Standup" :value="$fmtPct($rec['standupRate']['current'])" icon="user-group" tone="sky"
                :sub="$rec['standupAttended']['current'].' of '.$rec['standupMarked']['current'].' recorded'">
            <div class="mt-2">@include('partials.delta', ['change' => $rec['standupRate']['change'], 'unit' => ' pts', 'versus' => $versus])</div>
        </x-stat>
        <x-stat label="Tasks done" :value="$rec['completed']['current']" icon="check" tone="brand" sub="moved to Done">
            <div class="mt-2">@include('partials.delta', ['change' => $rec['completed']['change'], 'versus' => $versus])</div>
        </x-stat>
        <x-stat label="Cycle time" :value="$fmtDays($rec['cycleDays']['current'])" icon="clock" tone="violet" sub="In Progress → Done">
            <div class="mt-2">@include('partials.delta', ['change' => $rec['cycleDays']['change'], 'unit' => 'd', 'versus' => $versus, 'lowerIsBetter' => true])</div>
        </x-stat>
        <x-stat label="Rework" :value="$fmtPct($rec['reworkRate']['current'])" icon="arrow-left" tone="amber" sub="finished work that went through Recheck">
            <div class="mt-2">@include('partials.delta', ['change' => $rec['reworkRate']['change'], 'unit' => ' pts', 'versus' => $versus, 'lowerIsBetter' => true])</div>
        </x-stat>
        <x-stat label="Overdue now" :value="$rec['overdue']['current']" icon="exclamation-circle" tone="slate"
                :alert="$rec['overdue']['current'] > 0" sub="open tasks past due" />
    </div>
</section>

{{-- Lead notes --}}
<section class="card card-pad" aria-labelledby="notes-title">
    <h3 id="notes-title" class="card-title">Lead notes</h3>
    <p class="card-subtitle">Notes left with ratings over the last {{ count($weekLabels) }} weeks · visible to leads only</p>
    @if (empty($c['notes']))
        <p class="mt-5 text-sm text-slate-500">No notes yet. Add one while evaluating to give context to a score.</p>
    @else
        <ul class="mt-4 space-y-3">
            @foreach ($c['notes'] as $note)
                <li class="rounded-lg border border-slate-100 px-3 py-2.5">
                    <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                        @include('partials.perf-score', ['value' => (float) $note['score']])
                        <span class="font-medium text-slate-700">{{ $note['competency'] }}</span>
                        <span>· {{ $note['period'] }}</span>
                        @if ($note['evaluator'])<span>· {{ $note['evaluator'] }}</span>@endif
                    </div>
                    <p class="mt-1.5 text-sm text-slate-700">{{ $note['note'] }}</p>
                </li>
            @endforeach
        </ul>
    @endif
</section>
