{{-- One person's figures for sharing, with the tasks behind them. --}}
@php
    use App\Models\User;
    use App\Support\Duration;
    use App\Support\PeopleReportColumns as Columns;

    $person = $report['user'];
    $self = [['label' => $report['team']?->name ?? 'All teams', 'record' => $report['record']]];
    $byTeam = array_map(fn (array $t) => ['label' => $t['name'], 'record' => $t['record']], $report['byTeam']);
    $rows = count($byTeam) > 1 ? [...$byTeam, ...array_map(fn ($r) => $r + ['total' => true], $self)] : $self;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $person->name }}: analytics</title>
    @include('reports.people._pdf-styles')
</head>
<body>
    <h1>{{ $person->name }}</h1>
    <div class="meta">
        {{ User::ROLES[$person->role] ?? $person->role }}@if ($report['teams'] !== []), {{ implode(', ', $report['teams']) }}@endif.
        {{ $report['from']->format('j M Y') }} to {{ $report['to']->format('j M Y') }}, generated {{ now()->format('j M Y, H:i') }}.
    </div>
    <p class="note">Open, overdue and blocked counts are as of the day this was generated. "Too few" means there wasn't enough behind a rate to quote it. No figure here is combined into a score.</p>

    @foreach (Columns::GROUPS as $group => $label)
        <h2>{{ $label }}</h2>
        @include('reports.people._pdf-table', ['group' => $group, 'heading' => 'Scope', 'rows' => $rows])
    @endforeach

    <div class="group">
        <h2>Tasks finished ({{ count($report['finished']) }})</h2>
        @if ($report['finished'] === [])
            <p class="note">None in this period.</p>
        @else
            <table>
                <thead><tr><th>Task</th><th>Finished</th><th>Due</th><th class="num">Cycle</th><th class="num">Estimate</th><th class="num">Logged</th></tr></thead>
                <tbody>
                    @foreach ($report['finished'] as $row)
                        <tr>
                            <td>{{ $row['task']->reference() }} {{ $row['task']->title }}</td>
                            <td>{{ $row['completedAt']->format('j M') }}</td>
                            <td>{{ $row['task']->due_date?->format('j M') ?? '—' }}{{ $row['onTime'] === false ? ' (late)' : '' }}</td>
                            <td class="num">{{ $row['cycleDays'] !== null ? $row['cycleDays'].' d' : '—' }}</td>
                            <td class="num">{{ $row['task']->estimate_minutes ? Duration::format($row['task']->estimate_minutes) : '—' }}</td>
                            <td class="num">{{ (int) $row['task']->logged_minutes > 0 ? Duration::format((int) $row['task']->logged_minutes) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <h2>Open now ({{ count($report['open']) }})</h2>
        @if ($report['open'] === [])
            <p class="note">Nothing open.</p>
        @else
            <table>
                <thead><tr><th>Task</th><th>Status</th><th>Due</th><th class="num">Estimate</th><th class="num">Logged</th></tr></thead>
                <tbody>
                    @foreach ($report['open'] as $task)
                        <tr>
                            <td>{{ $task->reference() }} {{ $task->title }}{{ $task->isBlocked() ? ' (blocked)' : '' }}</td>
                            <td>{{ $task->statusLabel() }}</td>
                            <td>{{ $task->due_date?->format('j M') ?? '—' }}{{ $task->due_date?->lt(today()) ? ' (overdue)' : '' }}</td>
                            <td class="num">{{ $task->estimate_minutes ? Duration::format($task->estimate_minutes) : '—' }}</td>
                            <td class="num">{{ (int) $task->logged_minutes > 0 ? Duration::format((int) $task->logged_minutes) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</body>
</html>
