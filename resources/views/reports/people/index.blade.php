@php
    use App\Support\PeopleReportColumns as Columns;

    $query = array_filter([
        'from' => $filters['from']->toDateString(),
        'to' => $filters['to']->toDateString(),
        'team_id' => $filters['team_id'],
    ]);
    $team = $report['team'];
    $period = $report['from']->format('M j, Y').' to '.$report['to']->format('M j, Y');
    $before = $report['previous']['from']->format('M j').' to '.$report['previous']['to']->format('M j');
    $peopleCount = count($report['people']);
    $members = collect($report['people'])->whereNull('tag')->count();
    $headcount = $team && $members !== $peopleCount
        ? $members.' '.Str::plural('member', $members).' and '.($peopleCount - $members).' '.Str::plural('other', $peopleCount - $members)
        : $peopleCount.' '.Str::plural('person', $peopleCount);

    $personHref = fn (array $row) => route('reports.people.show', ['user' => $row['user']->id] + $query);
    $periodQuery = array_diff_key($query, ['team_id' => 0]);
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header title="People analytics" description="How each team and person worked, from the board, tasksheets, time logs, requests, ratings and KPI reviews.">
            <x-slot name="actions">
                <a href="{{ route('reports.people.export', $query) }}" data-turbo="false" class="btn-secondary btn-sm"><x-icon name="download" class="h-4 w-4" /> People CSV</a>
                @unless ($team)
                    <a href="{{ route('reports.people.export', $query + ['level' => 'teams']) }}" data-turbo="false" class="btn-secondary btn-sm"><x-icon name="download" class="h-4 w-4" /> Teams CSV</a>
                @endunless
                <a href="{{ route('reports.people.pdf', $query) }}" data-turbo="false" class="btn-secondary btn-sm"><x-icon name="download" class="h-4 w-4" /> PDF</a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container space-y-4" x-data="peopleTable({{ Js::from(array_keys(Columns::GROUPS)) }})">
            @include('reports._tabs')
            @include('reports.people._filters', ['action' => route('reports.people')])

            <p class="text-sm text-slate-600">
                <span class="font-medium text-slate-800">{{ $team?->name ?? 'All teams' }}</span>,
                {{ $period }}: {{ $headcount }}, {{ $report['workingDays'] }} working {{ Str::plural('day', $report['workingDays']) }}. Changes compare with {{ $before }}.
            </p>

            @if ($peopleCount === 0)
                <x-empty-state icon="user-group" title="No activity in this period." description="Try a longer period or another team." />
            @else
                @include('reports.people._tiles', ['record' => $report['summary'], 'compare' => $report['compare']])
                @include('reports.people._trends', ['trend' => $report['trend']])

                <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                    <h2 class="text-sm font-semibold text-slate-900">Figures</h2>
                    @include('reports.people._group-tabs')
                </div>

                @if ($report['teams'] !== [])
                    <section class="card min-w-0 overflow-hidden" aria-labelledby="teams-title">
                        <div class="card-header">
                            <h2 id="teams-title" class="card-title">By team</h2>
                            <span class="text-xs text-slate-500">Work counts for the team it belongs to</span>
                        </div>
                        @include('reports.people._table', [
                            'table' => 'teams',
                            'heading' => 'Team',
                            'rows' => $report['teams'],
                            'sortName' => fn (array $row) => $row['name'],
                            'name' => fn (array $row) => new \Illuminate\Support\HtmlString(view('reports.people._team-cell', ['row' => $row, 'periodQuery' => $periodQuery])->render()),
                        ])
                    </section>
                @endif

                <section class="card min-w-0 overflow-hidden" aria-labelledby="people-title">
                    <div class="card-header">
                        <h2 id="people-title" class="card-title">By person</h2>
                        <span class="text-xs text-slate-500">{{ $team ? 'Work on '.$team->name : 'All their work' }}</span>
                    </div>
                    @include('reports.people._table', [
                        'table' => 'people',
                        'heading' => 'Person',
                        'rows' => $report['people'],
                        'sortName' => fn (array $row) => $row['user']->name,
                        'name' => fn (array $row) => new \Illuminate\Support\HtmlString(view('reports.people._person-cell', ['row' => $row, 'href' => $personHref($row)])->render()),
                        'total' => $report['summary'],
                        'totalLabel' => 'All '.$peopleCount.' '.Str::plural('person', $peopleCount),
                    ])
                </section>

                @include('reports.people._definitions')

                <div class="space-y-1 text-xs text-slate-500">
                    <p>Open, overdue and blocked counts are as of today, whatever the period: the board keeps only the current status.</p>
                    @if ($team)
                        <p>Meetings, notes, comments and AI uses can't be split by team, so they show all of a person's activity.</p>
                    @else
                        <p>A person on two teams owes a tasksheet on each, so team days can add up to more than the total. Meetings, notes, comments and AI uses are summed over a team's members, so they count in each team.</p>
                    @endif
                    @if ($report['blockedHistoryFrom'])
                        <p>Blocked days only go back to {{ $report['blockedHistoryFrom']->format('M j, Y') }}, because older activity history is removed.</p>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
