@php
    use App\Services\FlowReportService;

    $weekNames = array_map(fn ($w) => $w['current'] ? 'This week' : $w['start']->format('M j'), $report['weeks']);
    $bandLook = [
        'done' => ['name' => 'Done', 'color' => '#10b981'],
        'recheck' => ['name' => 'Recheck', 'color' => '#be123c'],
        'in_review' => ['name' => 'In Review', 'color' => '#f59e0b'],
        'in_progress' => ['name' => 'In Progress', 'color' => '#2563eb'],
        'todo' => ['name' => 'To Do', 'color' => '#a78bfa'],
    ];
    $bands = array_map(fn ($key, $look) => $look + ['values' => array_column($report['flow'], $key)], array_keys($bandLook), $bandLook);
    $hasWork = collect($report['flow'])->flatten()->sum() > 0;
    $days = fn (?float $n) => $n === null ? '—' : rtrim(rtrim(number_format($n, 1), '0'), '.').' '.Str::plural('day', $n);
    $tileLabel = 'text-xs font-medium text-slate-500';
    $tileValue = 'tabular mt-1 text-xl font-semibold text-slate-900';
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Reports" description="How work moves through a team over the last {{ FlowReportService::WEEKS }} weeks." />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container space-y-4">
            @include('reports._tabs')

            <x-filter-bar :action="route('reports.flow')" :active="$teamId !== null">
                <label for="team_id" class="filter-label">Team</label>
                <select id="team_id" name="team_id" class="filter-select max-w-[14rem]">
                    <option value="">All teams</option>
                    @foreach ($teams as $team)
                        <option value="{{ $team->id }}" @selected($team->id === $teamId)>{{ $team->name }}</option>
                    @endforeach
                </select>
            </x-filter-bar>

            @unless ($hasWork)
                <x-empty-state icon="presentation-chart-line" title="Nothing to report yet." description="This fills in as tasks are added and finished." />
            @else
                <dl class="card grid grid-cols-2 gap-4 p-5 lg:grid-cols-4">
                    <div><dt class="{{ $tileLabel }}">In progress now</dt><dd class="{{ $tileValue }}">{{ $report['in_progress'] }}</dd></div>
                    <div><dt class="{{ $tileLabel }}">Finished in {{ FlowReportService::WEEKS }} weeks</dt><dd class="{{ $tileValue }}">{{ $report['finished'] }}</dd></div>
                    <div><dt class="{{ $tileLabel }}">Half finish within</dt><dd class="{{ $tileValue }}">{{ $days($report['cycle_p50']) }}</dd></div>
                    <div><dt class="{{ $tileLabel }}">85% finish within</dt><dd class="{{ $tileValue }}">{{ $days($report['cycle_p85']) }}</dd></div>
                </dl>

                <section class="card" aria-labelledby="flow-title">
                    <div class="card-header"><h2 id="flow-title" class="card-title">Work in each status</h2></div>
                    <div class="px-5 py-4 sm:px-6">
                        <p class="mb-3 text-sm text-slate-600">A widening band means work is piling up at that step.</p>
                        @include('partials.stacked-area-chart', ['label' => 'Tasks in each status at the end of each week', 'x' => $weekNames, 'bands' => $bands])
                    </div>
                </section>

                <div class="grid items-start gap-4 lg:grid-cols-2">
                    <section class="card min-w-0" aria-labelledby="throughput-title">
                        <div class="card-header"><h2 id="throughput-title" class="card-title">Tasks added and finished</h2></div>
                        <div class="px-5 py-4 sm:px-6">
                            @include('partials.line-chart', [
                                'label' => 'Tasks added and tasks finished each week',
                                'x' => $weekNames,
                                'series' => [
                                    ['name' => 'Added', 'color' => '#d97706', 'values' => $report['arrivals']],
                                    ['name' => 'Finished', 'color' => '#0b63f6', 'values' => $report['completions']],
                                ],
                            ])
                        </div>
                    </section>

                    <section class="card min-w-0" aria-labelledby="cycle-title">
                        <div class="card-header"><h2 id="cycle-title" class="card-title">Days to finish each task</h2></div>
                        <div class="px-5 py-4 sm:px-6">
                            @if (empty($report['cycle']))
                                <p class="text-sm text-slate-500">Shows here once tasks have gone from In Progress to Done.</p>
                            @else
                                @include('partials.scatter-chart', [
                                    'label' => 'Days from starting to finishing each task',
                                    'from' => $report['weeks'][0]['start'],
                                    'to' => now(),
                                    'points' => array_map(fn ($c) => [
                                        'at' => $c['completed_at'],
                                        'value' => $c['days'],
                                        'title' => $c['key'].' '.Str::limit($c['title'], 60),
                                        'note' => $days($c['days']).' · finished '.$c['completed_at']->format('M j'),
                                        'href' => route('tasks.show', $c['task_id']),
                                    ], $report['cycle']),
                                    'marker' => ['value' => $report['cycle_p85'], 'label' => '85% within '.$days($report['cycle_p85'])],
                                ])
                            @endif
                        </div>
                    </section>
                </div>

                @if (! empty($report['aging']))
                    <section class="card" aria-labelledby="aging-title">
                        <div class="card-header">
                            <h2 id="aging-title" class="card-title">Taking longer than usual</h2>
                            <span class="text-xs text-slate-500">In progress for more than {{ $days($report['cycle_p85']) }}</span>
                        </div>
                        <div class="table-wrap rounded-none border-0">
                            <table class="table-base">
                                <thead><tr><th scope="col">Task</th><th scope="col">Status</th><th scope="col" class="text-right">Days</th></tr></thead>
                                <tbody>
                                    @foreach ($report['aging'] as $row)
                                        <tr>
                                            <td class="min-w-0"><a href="{{ route('tasks.show', $row['task_id']) }}" class="text-sm font-medium text-slate-800 hover:text-brand-700"><span class="tabular text-slate-500">{{ $row['key'] }}</span> {{ $row['title'] }}</a></td>
                                            <td class="whitespace-nowrap text-sm text-slate-600">{{ $row['status'] }}</td>
                                            <td class="tabular text-right text-sm text-slate-700">{{ $row['days'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endif
            @endunless
        </div>
    </div>
</x-app-layout>
