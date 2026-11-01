@use('App\Support\ScoreColor')
{{--
    Deeper team analysis on the lead-only performance overview:
    competency trends, rating spread, and the objective record per member.
    Expects $o (PerformanceAnalytics::teamOverview), $team and $weekDate.
--}}
@php
    $fmtPct = fn (?int $v) => $v === null ? '—' : $v.'%';
    $fmtDays = fn (?float $v) => $v === null ? '—' : rtrim(rtrim(number_format($v, 1), '0'), '.').'d';
    $rw = $o['recordWindow'];
@endphp

<div class="grid gap-6 lg:grid-cols-3">
    {{-- Competency trends --}}
    <section class="card lg:col-span-2" aria-labelledby="competency-trends-title">
        <div class="card-header">
            <div>
                <h3 id="competency-trends-title" class="card-title">Competency trends</h3>
                <p class="card-subtitle">Team average per competency · last {{ count($o['trend']) }} weeks · change against the week before</p>
            </div>
        </div>
        @if (empty($o['competencyTrends']))
            <p class="px-5 py-6 text-sm text-slate-500 sm:px-6">No active competencies apply to this team’s members.</p>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($o['competencyTrends'] as $ct)
                    <li class="grid grid-cols-12 items-center gap-3 px-5 py-2.5 sm:px-6">
                        <div class="col-span-12 min-w-0 sm:col-span-4">
                            <p class="truncate text-sm font-medium text-slate-800">{{ $ct['competency']->name }}</p>
                            <p class="text-[11px] text-slate-400">{{ $ct['competency']->categoryLabel() }}</p>
                        </div>
                        <div class="col-span-7 sm:col-span-5 [&>*]:!h-8">
                            @include('partials.spark-line', [
                                'points' => collect($ct['points'])->map(fn ($p) => [...$p, 'title' => $p['label'].': '.ScoreColor::fmt($p['value'])])->all(),
                                'max' => 5, 'h' => 40,
                                'label' => $ct['competency']->name.' team average by week',
                            ])
                        </div>
                        <div class="col-span-5 flex items-center justify-end gap-2 sm:col-span-3">
                            @include('partials.delta', ['change' => $ct['change'], 'versus' => 'last week'])
                            @include('partials.perf-score', ['value' => $ct['current']])
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Score distribution --}}
    @php $dist = $o['distribution']; $dMax = max(max($dist['counts']), 1); @endphp
    <section class="card card-pad" aria-labelledby="distribution-title">
        <h3 id="distribution-title" class="card-title">Rating spread</h3>
        <p class="card-subtitle">All ratings given this week ({{ $dist['total'] }})</p>
        @if ($dist['total'] === 0)
            <p class="mt-6 text-sm text-slate-500">No ratings this week yet.
                <a href="{{ route('performance.evaluate', ['team' => $team->id]) }}" class="font-medium text-brand-700 hover:text-brand-800">Evaluate →</a>
            </p>
        @else
            <ul class="mt-4 space-y-2.5" role="img"
                aria-label="Ratings this week: {{ collect($dist['counts'])->map(fn ($n, $v) => $v.': '.$n)->implode(', ') }}">
                @foreach (array_reverse($dist['counts'], true) as $value => $count)
                    <li aria-hidden="true">
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="text-slate-600"><span class="tabular font-semibold text-slate-800">{{ $value }}</span> · {{ \App\Models\PerformanceScore::label($value) }}</span>
                            <span class="tabular text-slate-500">{{ $count }} <span class="text-slate-400">({{ $fmtPct($dist['pct'][$value]) }})</span></span>
                        </div>
                        <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full" style="width: {{ $count > 0 ? max(3, round($count / $dMax * 100)) : 0 }}%; background-color: {{ ScoreColor::hex((float) $value) }}"></div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>

{{-- Objective record --}}
<section class="card" aria-labelledby="record-title">
    <div class="card-header flex-wrap">
        <div>
            <h3 id="record-title" class="card-title">Objective record</h3>
            <p class="card-subtitle">
                {{ $rw['start']->format('M j') }} – {{ $rw['end']->format('M j') }} ({{ $rw['weeks'] }} weeks) · tasksheet on this team, tasks of this team or no team · archived tasks never count
            </p>
        </div>
        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-slate-500">Context — not part of the score</span>
    </div>
    @if ($o['members']->isEmpty())
        <p class="px-5 py-6 text-sm text-slate-500 sm:px-6">This team has no developer or QA members.</p>
    @else
        <div class="table-wrap">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-slate-500">
                        <th scope="col" class="px-5 py-2 font-medium sm:px-6">Member</th>
                        <th scope="col" class="px-3 py-2 text-right font-medium" title="Weekdays with a fully filled row, leave excluded">Tasksheet</th>
                        <th scope="col" class="px-3 py-2 text-right font-medium">Standup</th>
                        <th scope="col" class="px-3 py-2 text-right font-medium">Done</th>
                        <th scope="col" class="px-3 py-2 text-right font-medium">On time</th>
                        <th scope="col" class="px-3 py-2 text-right font-medium" title="In Progress → Done">Cycle</th>
                        <th scope="col" class="px-3 py-2 text-right font-medium" title="Share of finished work that went through Recheck">Rework</th>
                        <th scope="col" class="px-5 py-2 text-right font-medium sm:px-6">Overdue now</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($o['members'] as $member)
                        @php $r = $o['records'][$member->id]; @endphp
                        <tr>
                            <td class="px-5 py-2.5 sm:px-6">
                                <a href="{{ route('performance.members.show', ['user' => $member->id, 'team' => $team->id, 'week' => $weekDate->toDateString()]) }}"
                                   class="flex items-center gap-2 text-slate-800 hover:text-brand-700">
                                    <x-avatar :user="$member" size="sm" />
                                    <span class="truncate">{{ $member->name }}</span>
                                </a>
                            </td>
                            <td class="tabular px-3 py-2.5 text-right text-slate-700" title="{{ $r['daysLogged'] }} of {{ $r['daysExpected'] }} days">{{ $fmtPct($r['tasksheetRate']) }}</td>
                            <td class="tabular px-3 py-2.5 text-right text-slate-700" title="{{ $r['standupAttended'] }} of {{ $r['standupMarked'] }} recorded">{{ $fmtPct($r['standupRate']) }}</td>
                            <td class="tabular px-3 py-2.5 text-right font-semibold text-slate-900">{{ $r['completed'] }}</td>
                            <td class="tabular px-3 py-2.5 text-right text-slate-700">{{ $fmtPct($r['onTimeRate']) }}</td>
                            <td class="tabular px-3 py-2.5 text-right text-slate-700">{{ $fmtDays($r['cycleDays']) }}</td>
                            <td class="tabular px-3 py-2.5 text-right text-slate-700">{{ $fmtPct($r['reworkRate']) }}</td>
                            <td class="px-5 py-2.5 text-right sm:px-6">
                                @if ($r['overdue'] > 0)
                                    <span class="badge badge-warning tabular">{{ $r['overdue'] }}</span>
                                @else
                                    <span class="tabular text-slate-400">0</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                @php $t = $o['recordTotals']; @endphp
                <tfoot>
                    <tr class="border-t border-slate-200 bg-slate-50/60 text-xs font-medium text-slate-600">
                        <td class="px-5 py-2 sm:px-6">Team</td>
                        <td class="tabular px-3 py-2 text-right">{{ $fmtPct($t['tasksheetRate']) }}</td>
                        <td class="tabular px-3 py-2 text-right">{{ $fmtPct($t['standupRate']) }}</td>
                        <td class="tabular px-3 py-2 text-right">{{ $t['completed'] }}</td>
                        <td class="px-3 py-2"></td>
                        <td class="tabular px-3 py-2 text-right" title="Average of members' averages">{{ $fmtDays($t['cycleDays']) }}</td>
                        <td class="px-3 py-2"></td>
                        <td class="tabular px-5 py-2 text-right sm:px-6">{{ $t['overdue'] }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</section>
