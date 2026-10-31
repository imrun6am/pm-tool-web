{{--
    Print template for the KPI review PDF. Like tasksheet/report, plain tables
    only — dompdf supports a subset of CSS. A summary table for the whole
    period, then one page per member with the monthly rows and the drafts.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>KPI review</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 8.5px; color: #1e293b; margin: 0; }
        h1 { font-size: 15px; margin: 0 0 2px; }
        h2 { font-size: 12px; margin: 0 0 2px; }
        .meta { font-size: 8.5px; color: #64748b; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { border: 1px solid #cbd5e1; padding: 3px 4px; text-align: left; vertical-align: top; }
        th { background: #f1f5f9; font-size: 7.5px; text-transform: uppercase; letter-spacing: .03em; }
        td.num, th.num { text-align: center; }
        tr { page-break-inside: avoid; }
        .member { page-break-before: always; }
        .label { width: 22%; font-weight: bold; background: #f8fafc; }
        .muted { color: #94a3b8; }
        .notes { font-size: 7.5px; color: #64748b; }
        .notes p { margin: 0 0 2px; }
    </style>
</head>
<body>
    @php
        $fill = fn (string $status) => isset($statusColors[$status]) ? 'background:#'.$statusColors[$status] : '';
        $pct = fn ($p) => $p === null ? '—' : number_format($p, 1).'%';
    @endphp

    <h1>KPI review</h1>
    <div class="meta">
        {{ $report->scopeLabel() }} ·
        {{ $report->period_start->format('j M Y') }} – {{ $report->period_end->format('j M Y') }}
        · {{ count($members) }} {{ \Illuminate\Support\Str::plural('member', count($members)) }}
        · generated {{ now()->format('j M Y, H:i') }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Team member</th><th>Role</th><th>Team(s)</th>
                <th class="num">Working days</th><th class="num">Leave days</th>
                <th class="num">KPI / Task</th><th class="num">Actual</th><th class="num">Completion %</th>
                <th>Overall status</th><th>Success measure</th><th>Progress</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($members as $m)
                @php $p = $m['period']; @endphp
                <tr>
                    <td>{{ $p['name'] }}@if ($p['note'])<br><span class="muted">{{ $p['note'] }}</span>@endif</td>
                    <td>{{ $p['role'] }}</td>
                    <td>{{ $p['teams'] }}</td>
                    <td class="num">{{ $p['working_days'] }}</td>
                    <td class="num">{{ $p['leave_days'] }}</td>
                    <td class="num">{{ $p['kpi'] }}</td>
                    <td class="num">{{ $p['actual'] }}</td>
                    <td class="num">{{ $pct($p['percent']) }}</td>
                    <td style="{{ $fill($p['status']) }}">{{ $p['status'] }}</td>
                    <td>{{ $p['success'] }}</td>
                    <td>{{ $p['progress'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="notes">
        @foreach ($notes as $line)<p>{{ $line }}</p>@endforeach
    </div>

    @foreach ($members as $m)
        @php $p = $m['period']; @endphp
        <div class="member">
            <h2>{{ $p['name'] }}</h2>
            <div class="meta">{{ $p['role'] }} · {{ $p['teams'] }}</div>

            <table>
                <thead>
                    <tr>
                        <th>Period</th><th class="num">Working days</th><th class="num">Leave days</th>
                        <th class="num">KPI / Task</th><th class="num">Expected target</th><th class="num">Actual</th>
                        <th class="num">Completion %</th><th>Overall status</th><th>Success measure</th><th>Progress</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($m['months'] as $ym => $row)
                        <tr>
                            <td>{{ $monthLabels[$ym] }}</td>
                            <td class="num">{{ $row['working_days'] }}</td>
                            <td class="num">{{ $row['leave_days'] }}</td>
                            <td class="num">{{ $row['kpi'] }}</td>
                            <td class="num"></td>
                            <td class="num">{{ $row['actual'] }}</td>
                            <td class="num">{{ $pct($row['percent']) }}</td>
                            <td style="{{ $fill($row['status']) }}">{{ $row['status'] }}</td>
                            <td>{{ $row['success'] }}</td>
                            <td>{{ $row['progress'] }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td><strong>Whole period</strong></td>
                        <td class="num">{{ $p['working_days'] }}</td>
                        <td class="num">{{ $p['leave_days'] }}</td>
                        <td class="num">{{ $p['kpi'] }}</td>
                        <td class="num"></td>
                        <td class="num">{{ $p['actual'] }}</td>
                        <td class="num"><strong>{{ $pct($p['percent']) }}</strong></td>
                        <td style="{{ $fill($p['status']) }}"><strong>{{ $p['status'] }}</strong></td>
                        <td>{{ $p['success'] }}</td>
                        <td>{{ $p['progress'] }}</td>
                    </tr>
                </tbody>
            </table>

            <table>
                <tbody>
                    <tr><td class="label">Team Lead feedback (draft)</td><td>{{ $p['feedback'] ?: '—' }}</td></tr>
                    <tr><td class="label">Strengths (draft)</td><td>{{ $p['strengths'] ?: '—' }}</td></tr>
                    <tr><td class="label">Improvement areas (draft)</td><td>{{ $p['improvement_areas'] ?: '—' }}</td></tr>
                    <tr><td class="label">Improvement action / support (draft)</td><td>{{ $p['improvement_action'] ?: '—' }}</td></tr>
                    <tr><td class="label">Evidence / record</td><td>{!! nl2br(e($p['evidence'])) !!}</td></tr>
                    @if ($p['note'])<tr><td class="label">Note</td><td>{{ $p['note'] }}</td></tr>@endif
                </tbody>
            </table>

            @foreach ($m['months'] as $ym => $row)
                @continue($row['feedback'] === '')
                <table>
                    <thead><tr><th colspan="2">{{ $monthLabels[$ym] }} — drafts</th></tr></thead>
                    <tbody>
                        <tr><td class="label">Feedback</td><td>{{ $row['feedback'] }}</td></tr>
                        <tr><td class="label">Strengths</td><td>{{ $row['strengths'] }}</td></tr>
                        <tr><td class="label">Improvement areas</td><td>{{ $row['improvement_areas'] }}</td></tr>
                        <tr><td class="label">Improvement action</td><td>{{ $row['improvement_action'] }}</td></tr>
                    </tbody>
                </table>
            @endforeach
        </div>
    @endforeach
</body>
</html>
