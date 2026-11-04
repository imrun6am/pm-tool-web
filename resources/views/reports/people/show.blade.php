@php
    use App\Models\User;
    use App\Services\PeopleAnalyticsService;
    use App\Support\Duration;
    use App\Support\PeopleReportColumns as Columns;

    $person = $report['user'];
    $record = $report['record'];
    $query = array_filter([
        'from' => $filters['from']->toDateString(),
        'to' => $filters['to']->toDateString(),
        'team_id' => $filters['team_id'],
    ]);
    $team = $report['team'];
    $period = $report['from']->format('M j, Y').' to '.$report['to']->format('M j, Y');
    $before = $report['previous']['from']->format('M j').' to '.$report['previous']['to']->format('M j');
    $role = User::ROLES[$person->role] ?? $person->role;
    $description = $role.($report['teams'] !== [] ? ', '.implode(', ', $report['teams']) : ', no team');
    $limit = PeopleAnalyticsService::TASK_LIST_LIMIT;
    $finished = $report['finished'];
    $open = $report['open'];
    $categories = collect($report['categories'])->filter(fn ($c) => $c['value'] !== null);
    $kpi = $record['kpi'];
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$person->name" :description="$description"
                       :breadcrumbs="['People analytics' => route('reports.people', $query), $person->name => null]">
            <x-slot name="actions">
                <a href="{{ route('reports.people.tasks', ['user' => $person->id] + $query) }}" data-turbo="false" class="btn-secondary btn-sm"><x-icon name="download" class="h-4 w-4" /> Tasks CSV</a>
                <a href="{{ route('reports.people.person-pdf', ['user' => $person->id] + $query) }}" data-turbo="false" class="btn-secondary btn-sm"><x-icon name="download" class="h-4 w-4" /> PDF</a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container space-y-4" x-data="peopleTable({{ Js::from(array_keys(Columns::GROUPS)) }})">
            @include('reports.people._filters', ['action' => route('reports.people.show', $person->id)])

            <p class="text-sm text-slate-600">
                <span class="font-medium text-slate-800">{{ $team ? 'Work on '.$team->name : 'All their work' }}</span>,
                {{ $period }}. Changes compare with {{ $before }}.
                @if ($person->statusTag()) <span class="ml-1 rounded-full bg-slate-100 px-1.5 py-0.5 text-xs font-medium text-slate-600">{{ $person->statusTag() }}</span> @endif
            </p>

            @include('reports.people._tiles', ['record' => $record, 'compare' => $report['compare']])
            @include('reports.people._trends', ['trend' => $report['trend']])

            <div class="grid items-start gap-4 md:grid-cols-2 xl:grid-cols-3">
                @include('reports.people._facts', ['group' => 'delivery', 'slot' => null])
                @include('reports.people._facts', ['group' => 'workload', 'slot' => null])
                @include('reports.people._facts', ['group' => 'time', 'slot' => null])
                @include('reports.people._facts', ['group' => 'tasksheet', 'slot' => new \Illuminate\Support\HtmlString(
                    '<p class="border-t border-slate-100 px-5 py-2.5 text-sm"><a class="font-medium text-brand-700 hover:text-brand-800" href="'.e(route('tasksheet.user', ['member' => $person->id, 'from' => $query['from'], 'to' => $query['to']])).'">Open their tasksheet</a></p>'
                )])
                @include('reports.people._facts', ['group' => 'collaboration', 'slot' => null])

                <section class="card min-w-0" aria-labelledby="facts-ratings">
                    <div class="card-header"><h2 id="facts-ratings" class="card-title">{{ Columns::GROUPS['ratings'] }}</h2></div>
                    <dl class="divide-y divide-slate-100">
                        @foreach (Columns::visible('ratings') as $column)
                            @php $few = Columns::tooFew($column, $record); @endphp
                            <div class="flex items-baseline justify-between gap-4 px-5 py-2 text-sm" @isset($column['hint']) title="{{ $column['hint'] }}" @endisset>
                                <dt class="text-slate-600">{{ $column['label'] }}</dt>
                                @if ($few !== null)
                                    <dd class="whitespace-nowrap text-xs text-slate-500">Too few ({{ $few }} {{ Str::plural($column['unit'] ?? 'item', $few) }})</dd>
                                @else
                                    <dd class="tabular whitespace-nowrap font-medium text-slate-800">{{ Columns::display($column, $record) }}@if ($column['key'] === 'rated' && $record['ratings']['applicable'])<span class="font-normal text-slate-500"> of {{ $record['ratings']['applicable'] }}</span>@endif</dd>
                                @endif
                            </div>
                        @endforeach
                    </dl>
                    @if ($categories->isNotEmpty())
                        <ul class="space-y-2 border-t border-slate-100 px-5 py-3">
                            @foreach ($categories as $category)
                                <li>
                                    <div class="flex items-baseline justify-between gap-3 text-xs">
                                        <span class="truncate text-slate-600">{{ $category['label'] }}</span>
                                        <span class="tabular font-medium text-slate-800">{{ number_format($category['value'], 2) }}</span>
                                    </div>
                                    <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                        <div class="h-full rounded-full bg-brand-500" style="width: {{ round($category['value'] / 5 * 100) }}%"></div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <div class="space-y-1 border-t border-slate-100 px-5 py-2.5 text-sm">
                        @if ($kpi['from'])
                            <p class="text-xs text-slate-500">KPI from the review of {{ $kpi['from']->format('M j') }} to {{ $kpi['to']->format('M j, Y') }}. AI-drafted.</p>
                        @endif
                        <p class="flex flex-wrap gap-x-4">
                            @if ($report['teams'] !== [])
                                <a class="font-medium text-brand-700 hover:text-brand-800" href="{{ route('performance.members.show', array_filter(['user' => $person->id, 'team' => $filters['team_id']])) }}">Open performance</a>
                            @endif
                            <a class="font-medium text-brand-700 hover:text-brand-800" href="{{ route('kpi-reports.index') }}">KPI reports</a>
                        </p>
                    </div>
                </section>
            </div>

            @if (count($report['byTeam']) > 1)
                <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                    <h2 class="text-sm font-semibold text-slate-900">Split by team</h2>
                    @include('reports.people._group-tabs')
                </div>
                <section class="card min-w-0 overflow-hidden">
                    @include('reports.people._table', [
                        'table' => 'by-team',
                        'heading' => 'Team',
                        'rows' => $report['byTeam'],
                        'sortName' => fn (array $row) => $row['name'],
                        'name' => fn (array $row) => $row['name'],
                    ])
                </section>
                <p class="text-xs text-slate-500">Meetings, notes, comments and AI uses can't be split by team, so each row shows all of them. The tasksheet counts each team's sheet on its own.</p>
            @endif

            <section class="card min-w-0" aria-labelledby="finished-title">
                <div class="card-header">
                    <h2 id="finished-title" class="card-title">Tasks finished ({{ count($finished) }})</h2>
                    @if (count($finished) > $limit)<span class="text-xs text-slate-500">Latest {{ $limit }}. The CSV has them all.</span>@endif
                </div>
                @if ($finished === [])
                    <p class="px-5 py-4 text-sm text-slate-500">None in this period.</p>
                @else
                    <div class="table-wrap rounded-none border-0">
                        <table class="table-base [&_td]:!py-2.5">
                            <thead><tr>
                                <th scope="col">Task</th><th scope="col">Finished</th><th scope="col">Due</th>
                                <th scope="col" class="text-right">Cycle</th><th scope="col" class="text-right">Estimate</th><th scope="col" class="text-right">Logged</th>
                            </tr></thead>
                            <tbody>
                                @foreach (array_slice($finished, 0, $limit) as $row)
                                    <tr>
                                        <td class="min-w-0 max-w-md">
                                            <a href="{{ route('tasks.show', $row['task']->id) }}" class="block truncate text-sm font-medium text-slate-800 hover:text-brand-700"><span class="tabular text-slate-500">{{ $row['task']->reference() }}</span> {{ $row['task']->title }}</a>
                                            @if ($row['reworked'])<span class="text-xs text-slate-500">Went back to Recheck</span>@endif
                                        </td>
                                        <td class="tabular whitespace-nowrap text-sm text-slate-700">{{ $row['completedAt']->format('M j') }}</td>
                                        <td class="tabular whitespace-nowrap text-sm text-slate-600">
                                            {{ $row['task']->due_date?->format('M j') ?? '—' }}
                                            @if ($row['onTime'] === false)<span class="ml-1 text-xs font-medium text-warning-700">late</span>@endif
                                        </td>
                                        <td class="tabular whitespace-nowrap text-right text-sm text-slate-700">{{ $row['cycleDays'] !== null ? $row['cycleDays'].' d' : '—' }}</td>
                                        <td class="tabular whitespace-nowrap text-right text-sm text-slate-600">{{ $row['task']->estimate_minutes ? Duration::format($row['task']->estimate_minutes) : '—' }}</td>
                                        <td class="tabular whitespace-nowrap text-right text-sm text-slate-700">{{ (int) $row['task']->logged_minutes > 0 ? Duration::format((int) $row['task']->logged_minutes) : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="card min-w-0" aria-labelledby="open-title">
                <div class="card-header">
                    <h2 id="open-title" class="card-title">Open now ({{ count($open) }})</h2>
                    @if (count($open) > $limit)<span class="text-xs text-slate-500">Soonest due {{ $limit }}. The CSV has them all.</span>@endif
                </div>
                @if ($open === [])
                    <p class="px-5 py-4 text-sm text-slate-500">Nothing open.</p>
                @else
                    <div class="table-wrap rounded-none border-0">
                        <table class="table-base [&_td]:!py-2.5">
                            <thead><tr>
                                <th scope="col">Task</th><th scope="col">Status</th><th scope="col">Due</th>
                                <th scope="col" class="text-right">Estimate</th><th scope="col" class="text-right">Logged</th>
                            </tr></thead>
                            <tbody>
                                @foreach (array_slice($open, 0, $limit) as $task)
                                    <tr>
                                        <td class="min-w-0 max-w-md">
                                            <a href="{{ route('tasks.show', $task->id) }}" class="block truncate text-sm font-medium text-slate-800 hover:text-brand-700"><span class="tabular text-slate-500">{{ $task->reference() }}</span> {{ $task->title }}</a>
                                            @if ($task->isBlocked())<span class="text-xs font-medium text-warning-700">Blocked</span>@endif
                                        </td>
                                        <td class="whitespace-nowrap text-sm text-slate-600">{{ $task->statusLabel() }}</td>
                                        <td class="tabular whitespace-nowrap text-sm text-slate-600">
                                            {{ $task->due_date?->format('M j') ?? '—' }}
                                            @if ($task->due_date?->lt(today()))<span class="ml-1 text-xs font-medium text-warning-700">overdue</span>@endif
                                        </td>
                                        <td class="tabular whitespace-nowrap text-right text-sm text-slate-600">{{ $task->estimate_minutes ? Duration::format($task->estimate_minutes) : '—' }}</td>
                                        <td class="tabular whitespace-nowrap text-right text-sm text-slate-700">{{ (int) $task->logged_minutes > 0 ? Duration::format((int) $task->logged_minutes) : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <div class="space-y-1 text-xs text-slate-500">
                <p>Open, overdue and blocked counts are as of today, whatever the period. No figure here is combined into a score.</p>
                @if ($report['blockedHistoryFrom'])
                    <p>Blocked days only go back to {{ $report['blockedHistoryFrom']->format('M j, Y') }}, because older activity history is removed.</p>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
