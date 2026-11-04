{{-- Scope against finished work and the forecast. Requires $release, $burnUp, $forecast. --}}
@php
    $dates = $forecast['dates'];
    $tileLabel = 'text-xs font-medium text-slate-500';
    $tileValue = 'tabular mt-0.5 text-lg font-semibold text-slate-900';
@endphp
<section class="card" aria-labelledby="progress-title">
    <div class="card-header">
        <h2 id="progress-title" class="card-title flex items-center gap-2">
            <x-icon name="presentation-chart-line" variant="outline" class="h-4 w-4 text-slate-400" />Progress
        </h2>
        <span class="text-xs text-slate-500">Not counting subtasks · planned end {{ $release->end_date->format('M j, Y') }}</span>
    </div>
    <div class="px-5 py-4 sm:px-6">
        @if (collect($burnUp)->sum('scope') === 0)
            <p class="text-sm text-slate-500">Progress shows here once this release has tasks.</p>
        @else
            @include('partials.line-chart', [
                'label' => 'Total tasks and finished tasks over the release, currently '.last($burnUp)['done'].' finished of '.last($burnUp)['scope'],
                'x' => array_map(fn ($point) => $point['date']->format('M j'), $burnUp),
                'series' => [
                    ['name' => 'Scope', 'color' => '#d97706', 'values' => array_column($burnUp, 'scope')],
                    ['name' => 'Done', 'color' => '#0b63f6', 'values' => array_column($burnUp, 'done')],
                ],
            ])

            <div class="mt-4 border-t border-slate-100 pt-4">
                @if ($release->completed_at)
                    <p class="text-sm text-slate-600">Completed on {{ $release->completed_at->format('M j, Y') }}.</p>
                @elseif ($forecast['remaining'] === 0)
                    <p class="text-sm text-slate-600">Nothing left to finish.</p>
                @elseif (! $dates)
                    <p class="text-sm text-slate-600">No forecast yet. It needs this team to have finished work in at least 3 of the last {{ \App\Services\ReleaseProgressService::HISTORY_WEEKS }} weeks.</p>
                @else
                    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div><dt class="{{ $tileLabel }}">Likely finish</dt><dd class="{{ $tileValue }}">{{ $dates['p50']->format('M j') }}</dd></div>
                        <div><dt class="{{ $tileLabel }}">Fairly safe by</dt><dd class="{{ $tileValue }}">{{ $dates['p85']->format('M j') }}</dd></div>
                        <div><dt class="{{ $tileLabel }}">Chance of making {{ $release->end_date->format('M j') }}</dt><dd class="{{ $tileValue }}">{{ $forecast['on_time_pct'] }}%</dd></div>
                    </dl>
                    <p class="mt-3 text-xs leading-5 text-slate-500">
                        A likelihood, not a promise: {{ $forecast['remaining'] }} open {{ Str::plural('task', $forecast['remaining']) }}, replayed against this team’s last {{ $forecast['weeks_sampled'] }} weeks (about {{ $forecast['avg_per_week'] }} finished a week). It assumes no more tasks are added.
                    </p>
                @endif
            </div>
        @endif
    </div>
</section>
