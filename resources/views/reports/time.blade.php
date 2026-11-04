@php
    use App\Support\Duration;

    $hours = fn (int $minutes) => Duration::format($minutes);
    $accuracy = $report['accuracy'];
    $tileLabel = 'text-xs font-medium text-slate-500';
    $tileValue = 'tabular mt-1 text-xl font-semibold text-slate-900';
    $query = array_filter([
        'from' => $filters['from']->toDateString(),
        'to' => $filters['to']->toDateString(),
        'team_id' => $filters['team_id'],
        'release_id' => $filters['release_id'],
    ]);
    $groups = ['Release' => $report['by_release'], 'Team' => $report['by_team'], 'Person' => $report['by_person']];
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Reports" description="Where logged hours went, and how they compare with the estimates." />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container space-y-4">
            @include('reports._tabs')

            <x-filter-bar :action="route('reports.time')" :active="$filters['team_id'] || $filters['release_id']">
                <label for="from" class="filter-label">From</label>
                <input id="from" type="date" name="from" value="{{ $filters['from']->toDateString() }}" class="filter-select w-auto">
                <label for="to" class="filter-label">To</label>
                <input id="to" type="date" name="to" value="{{ $filters['to']->toDateString() }}" class="filter-select w-auto">
                <label for="team_id" class="filter-label">Team</label>
                <select id="team_id" name="team_id" class="filter-select max-w-[12rem]">
                    <option value="">{{ auth()->user()->canOverseeAllTeams() ? 'All teams' : 'My teams' }}</option>
                    @foreach ($teams as $team)
                        <option value="{{ $team->id }}" @selected($team->id === $filters['team_id'])>{{ $team->name }}</option>
                    @endforeach
                </select>
                <label for="release_id" class="filter-label">Release</label>
                <select id="release_id" name="release_id" class="filter-select max-w-[14rem]">
                    <option value="">All releases</option>
                    @foreach ($releases as $release)
                        <option value="{{ $release->id }}" @selected($release->id === $filters['release_id'])>{{ $release->name }}</option>
                    @endforeach
                </select>
                @if ($report['total_minutes'] > 0)
                    <a href="{{ route('reports.time.export', $query) }}" data-turbo="false" class="btn-secondary btn-sm">Export CSV</a>
                @endif
            </x-filter-bar>

            <dl class="card grid grid-cols-2 gap-4 p-5 lg:grid-cols-4">
                <div><dt class="{{ $tileLabel }}">Hours logged</dt><dd class="{{ $tileValue }}">{{ $hours($report['total_minutes']) }}</dd></div>
                <div><dt class="{{ $tileLabel }}">Tasks compared</dt><dd class="{{ $tileValue }}">{{ $accuracy['tasks'] }}</dd></div>
                <div><dt class="{{ $tileLabel }}">Estimated on those</dt><dd class="{{ $tileValue }}">{{ $accuracy['tasks'] > 0 ? $hours($accuracy['estimated_minutes']) : '—' }}</dd></div>
                <div>
                    <dt class="{{ $tileLabel }}">Logged against estimate</dt>
                    <dd class="{{ $tileValue }}">{{ $accuracy['ratio'] !== null ? round($accuracy['ratio'] * 100).'%' : '—' }}</dd>
                </div>
            </dl>

            @if ($report['total_minutes'] === 0)
                <x-empty-state icon="clock" title="No hours logged in this period." description="Hours show here once people log time on their tasks." />
            @else
                <div class="grid items-start gap-4 lg:grid-cols-3">
                    @foreach ($groups as $heading => $rows)
                        <section class="card min-w-0">
                            <div class="card-header"><h2 class="card-title">By {{ Str::lower($heading) }}</h2></div>
                            <ul class="divide-y divide-slate-100">
                                @foreach (array_slice($rows, 0, 15) as $row)
                                    <li class="px-5 py-2.5">
                                        <div class="flex items-baseline justify-between gap-3 text-sm">
                                            <span class="min-w-0 truncate text-slate-700">{{ $row['name'] }}</span>
                                            <span class="tabular whitespace-nowrap font-medium text-slate-800">{{ $hours($row['minutes']) }}</span>
                                        </div>
                                        <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                            <div class="h-full rounded-full bg-brand-500" style="width: {{ round($row['minutes'] / max(1, $report['total_minutes']) * 100) }}%"></div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endforeach
                </div>
            @endif

            @if (! empty($accuracy['misses']))
                <section class="card" aria-labelledby="misses-title">
                    <div class="card-header">
                        <h2 id="misses-title" class="card-title">Furthest from the estimate</h2>
                        <span class="text-xs text-slate-500">Tasks finished in this period</span>
                    </div>
                    <div class="table-wrap rounded-none border-0">
                        <table class="table-base">
                            <thead><tr><th scope="col">Task</th><th scope="col" class="text-right">Estimated</th><th scope="col" class="text-right">Logged</th></tr></thead>
                            <tbody>
                                @foreach ($accuracy['misses'] as $row)
                                    <tr>
                                        <td class="min-w-0"><a href="{{ route('tasks.show', $row['task_id']) }}" class="text-sm font-medium text-slate-800 hover:text-brand-700"><span class="tabular text-slate-500">{{ $row['key'] }}</span> {{ $row['title'] }}</a></td>
                                        <td class="tabular whitespace-nowrap text-right text-sm text-slate-600">{{ $hours($row['estimated_minutes']) }}</td>
                                        <td class="tabular whitespace-nowrap text-right text-sm text-slate-800">{{ $hours($row['logged_minutes']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            <p class="text-xs text-slate-500">For planning only. These figures don’t feed performance scores or KPI reports.</p>
        </div>
    </div>
</x-app-layout>
