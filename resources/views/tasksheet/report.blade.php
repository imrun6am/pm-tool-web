{{--
    Print template for the tasksheet PDF. Deliberately not the screen layout:
    dompdf supports a subset of CSS, and the interactive sheet is Tailwind plus
    inline forms, none of which belongs in a document. Plain tables only.

    `feedback` renders only when $includeFeedback — which the controller derives
    from the *requesting* user, never from whose rows these are.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Tasksheet report</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 9px; color: #1e293b; margin: 0; }
        h1 { font-size: 15px; margin: 0 0 2px; }
        .meta { font-size: 9px; color: #64748b; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 4px 5px; text-align: left; vertical-align: top; }
        th { background: #f1f5f9; font-size: 8px; text-transform: uppercase; letter-spacing: .04em; }
        td.num, th.num { text-align: center; }
        tr { page-break-inside: avoid; }
        .day { background: #e2e8f0; font-weight: bold; }
        .muted { color: #94a3b8; }
        .leave { color: #0369a1; }
        .yes { color: #15803d; font-weight: bold; }
        .no { color: #b91c1c; font-weight: bold; }
        .rt p { margin: 0 0 3px; }
        .rt ul, .rt ol { margin: 0 0 3px; padding-left: 14px; }
        .empty { text-align: center; padding: 24px; color: #64748b; }
    </style>
</head>
<body>
    <h1>Tasksheet report</h1>
    <div class="meta">
        @if ($member)<strong>{{ $member->name }}</strong> ·@endif
        @if ($team){{ $team->name }} ·@endif
        {{ $from === $to ? \Illuminate\Support\Carbon::parse($from)->format('D, M j, Y') : \Illuminate\Support\Carbon::parse($from)->format('M j, Y').' – '.\Illuminate\Support\Carbon::parse($to)->format('M j, Y') }}
        · {{ $rows->count() }} {{ \Illuminate\Support\Str::plural('row', $rows->count()) }}
        · generated {{ now()->format('M j, Y H:i') }} by {{ $generatedBy->name }}
    </div>

    @if ($rows->isEmpty())
        <div class="empty">No tasksheet rows for this selection.</div>
    @else
        <table>
            <thead>
                <tr>
                    <th style="width:11%">Member</th>
                    <th class="num" style="width:6%">Standup</th>
                    <th style="width:18%">Plan</th>
                    <th style="width:18%">Result</th>
                    <th style="width:13%">Comment</th>
                    <th class="num" style="width:5%">WP</th>
                    <th style="width:10%">Tickets</th>
                    <th class="num" style="width:5%">Cnt</th>
                    <th class="num" style="width:5%">Pts</th>
                    @if ($includeFeedback)<th style="width:13%">Feedback</th>@endif
                </tr>
            </thead>
            <tbody>
                @php $currentDay = null; @endphp
                @foreach ($rows as $row)
                    @if ($currentDay !== $row->date->toDateString())
                        @php $currentDay = $row->date->toDateString(); @endphp
                        <tr class="day">
                            <td colspan="{{ $includeFeedback ? 10 : 9 }}">{{ $row->date->format('l, M j, Y') }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td>
                            {{ $row->member->name ?? 'Unknown' }}
                            @if (! $member && $row->team)<div class="muted">{{ $row->team->name }}</div>@endif
                        </td>
                        <td class="num">
                            @if ($row->isFullDayLeave())
                                <span class="muted">—</span>
                            @elseif ($row->attendedStandup())
                                <span class="yes">Yes</span>
                            @elseif ($row->missedStandup())
                                <span class="no">No</span>
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        @if ($row->isFullDayLeave())
                            <td colspan="7" class="leave">{{ $row->leaveLabel() }}</td>
                        @else
                            <td><div class="rt">{!! $row->html('plan') !!}</div></td>
                            <td><div class="rt">{!! $row->html('result') !!}</div></td>
                            <td><div class="rt">{!! $row->html('comment') !!}</div></td>
                            <td class="num">{{ $row->work_points ?? '—' }}</td>
                            <td><div class="rt">{!! $row->html('tickets') !!}</div></td>
                            <td class="num">{{ $row->ticket_count ?? '—' }}</td>
                            <td class="num">{{ $row->ticket_points ?? '—' }}</td>
                        @endif
                        @if ($includeFeedback)
                            <td><div class="rt">{!! $row->html('feedback') !!}</div></td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
