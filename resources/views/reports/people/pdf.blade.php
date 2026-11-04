{{-- The people report for sharing: every group of figures, per team and per person. --}}
@php
    use App\Models\User;
    use App\Support\PeopleReportColumns as Columns;

    $teamRows = array_map(fn (array $t) => [
        'label' => $t['name'],
        'sub' => $t['id'] ? $t['people'].' '.Str::plural('person', $t['people']).($t['lead'] ? ', led by '.$t['lead']->name : '') : null,
        'record' => $t['record'],
    ], $report['teams']);
    $peopleRows = array_map(fn (array $p) => [
        'label' => $p['user']->name,
        'sub' => trim((User::ROLES[$p['user']->role] ?? $p['user']->role).($p['teams'] ? ', '.implode(', ', $p['teams']) : '').($p['tag'] ? ' ('.$p['tag'].')' : '')),
        'record' => $p['record'],
    ], $report['people']);
    $peopleRows[] = ['label' => 'All '.count($report['people']).' '.Str::plural('person', count($report['people'])), 'record' => $report['summary'], 'total' => true];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>People analytics</title>
    @include('reports.people._pdf-styles')
</head>
<body>
    <h1>People analytics: {{ $report['team']?->name ?? 'All teams' }}</h1>
    <div class="meta">
        {{ $report['from']->format('j M Y') }} to {{ $report['to']->format('j M Y') }},
        {{ $report['workingDays'] }} working {{ Str::plural('day', $report['workingDays']) }}.
        Generated {{ now()->format('j M Y, H:i') }}.
    </div>
    <p class="note">Open, overdue and blocked counts are as of the day this was generated. "Too few" means there wasn't enough behind a rate to quote it; the number in brackets is how many there were. No figure here is combined into a score.</p>
    @if ($report['blockedHistoryFrom'])
        <p class="note">Blocked days only go back to {{ $report['blockedHistoryFrom']->format('j M Y') }}, because older activity history is removed.</p>
    @endif

    @foreach (Columns::GROUPS as $group => $label)
        <div @class(['group' => ! $loop->first])>
            <h2>{{ $label }}</h2>
            @if ($teamRows !== [])
                <h3>By team</h3>
                @include('reports.people._pdf-table', ['group' => $group, 'heading' => 'Team', 'rows' => $teamRows])
            @endif
            <h3>By person</h3>
            @include('reports.people._pdf-table', ['group' => $group, 'heading' => 'Person', 'rows' => $peopleRows])
        </div>
    @endforeach
</body>
</html>
