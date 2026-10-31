@use('App\Support\ScoreColor')
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('performance.index', ['team' => $team->id, 'week' => $weekDate->toDateString()]) }}" class="btn-secondary btn-sm !px-2" aria-label="Back to overview">‹</a>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="page-title">{{ $member->name }}</h2>
                        @include('partials.role-badge', ['role' => $member->role, 'variant' => 'pill'])
                        @if ($member->statusTag())
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-500">{{ $member->statusTag() }}</span>
                        @endif
                    </div>
                    <p class="text-xs text-slate-400">{{ $team->name }} · {{ $card['weekLabel'] }}</p>
                </div>
            </div>

            <div class="flex items-center gap-1">
                <a href="{{ route('performance.members.show', ['user' => $member->id, 'team' => $team->id, 'week' => $prevWeek->toDateString()]) }}" class="btn-secondary btn-sm !px-2" aria-label="Previous week">‹</a>
                <a href="{{ route('performance.members.show', ['user' => $member->id, 'team' => $team->id, 'week' => $nextWeek->toDateString()]) }}" class="btn-secondary btn-sm !px-2" aria-label="Next week">›</a>
                <a href="{{ route('performance.members.show', ['user' => $member->id, 'team' => $team->id]) }}" class="btn-ghost btn-sm ml-1">This week</a>
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container space-y-6">
            @php
                $c = $card;
                $overall = $c['overall'];
                $radarAxes = collect($c['categories'])->map(fn ($cat) => ['label' => $cat['label'], 'value' => $cat['value']])->values()->all();
                $circ = 2 * M_PI * 52;
                $frac = $overall !== null ? $overall / 5 : 0;
            @endphp

            @if ($overall === null)
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500">
                    {{ $member->name }} has not been evaluated for this week yet.
                    <a href="{{ route('performance.evaluate', ['team' => $team->id]) }}" class="font-medium text-brand-600">Evaluate now →</a>
                </div>
            @endif

            {{-- Score + radar + trend --}}
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="card card-pad flex flex-col items-center justify-center">
                    <div class="relative h-40 w-40">
                        <svg viewBox="0 0 120 120" class="h-40 w-40 -rotate-90">
                            <circle cx="60" cy="60" r="52" fill="none" stroke="#f1f5f9" stroke-width="12" />
                            @if ($overall !== null)
                                <circle cx="60" cy="60" r="52" fill="none" stroke="{{ ScoreColor::hex($overall) }}" stroke-width="12"
                                        stroke-linecap="round" stroke-dasharray="{{ $circ }}" stroke-dashoffset="{{ $circ * (1 - $frac) }}" />
                            @endif
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-4xl font-semibold tracking-tight" style="color: {{ ScoreColor::hex($overall) }}">{{ ScoreColor::fmt($overall) }}</span>
                            <span class="text-xs text-slate-400">out of 5</span>
                        </div>
                    </div>
                    <p class="mt-3 text-sm font-medium text-slate-700">{{ ScoreColor::label($overall) }}</p>
                    <p class="text-xs text-slate-400">
                        {{ $overall !== null ? ScoreColor::pct($overall).'%' : '—' }} · {{ $c['ratedCount'] }}/{{ $c['applicableCount'] }} competencies rated
                    </p>
                </div>

                <div class="card card-pad">
                    <h3 class="text-sm font-semibold text-slate-900">Category breakdown</h3>
                    @include('partials.radar', ['axes' => $radarAxes, 'max' => 5])
                </div>

                <div class="card card-pad">
                    <h3 class="text-sm font-semibold text-slate-900">Overall trend</h3>
                    <p class="mt-0.5 text-xs text-slate-400">Weighted score · last {{ count($c['overallTrend']) }} weeks</p>
                    <div class="mt-3">@include('partials.spark-line', ['points' => $c['overallTrend'], 'max' => 5])</div>
                    <div class="mt-2 flex justify-between text-[10px] text-slate-400">
                        <span>{{ $c['overallTrend'][0]['label'] ?? '' }}</span>
                        <span>{{ $c['overallTrend'][count($c['overallTrend']) - 1]['label'] ?? '' }}</span>
                    </div>
                </div>
            </div>

            {{-- Per-competency detail --}}
            <div class="card card-pad">
                <h3 class="text-sm font-semibold text-slate-900">Competencies</h3>
                <p class="mt-0.5 text-xs text-slate-400">This week’s rating, latest trend, and private notes</p>
                <div class="mt-4 divide-y divide-slate-100">
                    @foreach ($c['competencies'] as $row)
                        <div class="grid grid-cols-1 gap-3 py-3 sm:grid-cols-12 sm:items-center">
                            <div class="sm:col-span-5">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium text-slate-800">{{ $row['competency']->name }}</span>
                                    <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-slate-500">{{ $row['competency']->categoryLabel() }}</span>
                                    <span class="text-[10px] text-slate-400">×{{ $row['competency']->weight }}</span>
                                </div>
                                @if ($row['note'])
                                    <p class="mt-1 flex items-start gap-1 text-xs text-slate-500">
                                        <x-icon name="note" class="mt-0.5 h-3 w-3 flex-none text-slate-400" />
                                        <span>{{ $row['note'] }}@if ($row['evaluator'])<span class="text-slate-400"> — {{ $row['evaluator'] }}</span>@endif</span>
                                    </p>
                                @endif
                            </div>
                            <div class="sm:col-span-4">@include('partials.spark-line', ['points' => collect($row['trend'])->map(fn ($v) => ['value' => $v])->all(), 'max' => 5, 'h' => 40])</div>
                            <div class="flex items-center justify-between sm:col-span-3 sm:justify-end sm:gap-3">
                                <span class="text-xs text-slate-400 sm:hidden">This week</span>
                                @include('partials.perf-score', ['value' => $row['score'], 'showLabel' => true])
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Objective panels — context, not part of the score --}}
            <div>
                <div class="mb-3 flex items-center gap-2">
                    <h3 class="text-sm font-semibold text-slate-900">Objective signals</h3>
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-slate-400">Context — not part of the score</span>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @include('partials.stat-tile', ['label' => 'Work points', 'value' => $c['tasksheet']['workPoints'], 'sub' => $c['tasksheet']['ticketCount'].' tickets · '.$c['tasksheet']['ticketPoints'].' pts', 'icon' => 'activity', 'tone' => 'brand'])
                    @include('partials.stat-tile', ['label' => 'On-time fill', 'value' => $c['tasksheet']['onTimePct'] !== null ? $c['tasksheet']['onTimePct'].'%' : '—', 'sub' => $c['tasksheet']['present'].' days present · '.$c['tasksheet']['leaveDays'].' on leave', 'icon' => 'calendar', 'tone' => 'sky'])
                    @include('partials.stat-tile', ['label' => 'Tasks done', 'value' => $c['board']['done'].'/'.$c['board']['assigned'], 'sub' => ($c['board']['donePct'] !== null ? $c['board']['donePct'].'% complete' : 'none assigned').' · '.$c['board']['open'].' open', 'icon' => 'board', 'tone' => 'emerald'])
                    @include('partials.stat-tile', ['label' => 'Rework rate', 'value' => $c['board']['reworkPct'] !== null ? $c['board']['reworkPct'].'%' : '—', 'sub' => $c['board']['rework'].' in recheck', 'icon' => 'restore', 'tone' => ($c['board']['reworkPct'] ?? 0) > 20 ? 'rose' : 'slate'])
                </div>

                <div class="card card-pad mt-4">
                    <div class="flex items-center justify-between">
                        <h4 class="text-sm font-semibold text-slate-900">Work points this week</h4>
                        <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-slate-400">Tasksheet</span>
                    </div>
                    @php $wMax = collect($c['tasksheet']['daily'])->max('value') ?: 0; @endphp
                    <div class="mt-4 flex h-24 items-end gap-2">
                        @foreach ($c['tasksheet']['daily'] as $d)
                            <div class="flex flex-1 flex-col items-center justify-end gap-1" title="{{ $d['label'] }}: {{ $d['value'] }} work points">
                                @if ($d['value'] > 0)<span class="text-[10px] font-medium text-slate-400">{{ $d['value'] }}</span>@endif
                                <div class="w-full max-w-[2rem] rounded-t bg-brand-400" style="height: {{ $wMax > 0 ? max((int) round($d['value'] / $wMax * 100), $d['value'] > 0 ? 8 : 0) : 0 }}%"></div>
                                <span class="text-[10px] text-slate-400">{{ $d['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Score history --}}
            <div class="card card-pad">
                <h3 class="text-sm font-semibold text-slate-900">Recent ratings</h3>
                @if (empty($c['history']))
                    <p class="mt-6 text-sm text-slate-400">No ratings recorded yet.</p>
                @else
                    <ul class="mt-4 space-y-2">
                        @foreach ($c['history'] as $h)
                            <li class="flex items-start gap-3 rounded-lg px-2 py-2 hover:bg-slate-50">
                                @include('partials.perf-score', ['value' => (float) $h['score']])
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm text-slate-700">
                                        <span class="font-medium">{{ $h['competency'] }}</span>
                                        <span class="text-slate-400">· {{ $h['label'] }}</span>
                                    </p>
                                    @if ($h['note'])<p class="text-xs text-slate-500">{{ $h['note'] }}</p>@endif
                                </div>
                                <div class="flex-none text-right text-[11px] text-slate-400">
                                    <div>{{ $h['period'] }}</div>
                                    @if ($h['evaluator'])<div>by {{ $h['evaluator'] }}</div>@endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
