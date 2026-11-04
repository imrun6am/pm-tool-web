{{-- Finished tasks and hours logged, as two charts: they don't share a scale. --}}
@php
    $unit = $trend['unit'];
    $last = array_key_last($trend['buckets']);
    $range = fn (array $b) => $b['start']->format('M j').' to '.$b['end']->format('M j');
@endphp
<div class="grid items-start gap-4 lg:grid-cols-2">
    <section class="card min-w-0" aria-labelledby="trend-finished">
        <div class="card-header"><h2 id="trend-finished" class="card-title">Tasks finished per {{ $unit }}</h2></div>
        <div class="px-5 pb-4 sm:px-6">
            @include('partials.column-chart', [
                'label' => 'Tasks finished per '.$unit,
                'bars' => array_map(fn (array $b, int $i) => [
                    'label' => $b['label'],
                    'value' => $b['completed'],
                    'current' => $i === $last,
                    'title' => $b['completed'].' '.Str::plural('task', $b['completed']).', '.$range($b),
                ], $trend['buckets'], array_keys($trend['buckets'])),
            ])
        </div>
    </section>

    <section class="card min-w-0" aria-labelledby="trend-hours">
        <div class="card-header"><h2 id="trend-hours" class="card-title">Hours logged per {{ $unit }}</h2></div>
        <div class="px-5 pb-4 sm:px-6">
            @include('partials.column-chart', [
                'label' => 'Hours logged per '.$unit,
                'bars' => array_map(fn (array $b, int $i) => [
                    'label' => $b['label'],
                    'value' => round($b['minutes'] / 60, 1),
                    'current' => $i === $last,
                    'title' => \App\Support\Duration::format($b['minutes']).', '.$range($b),
                ], $trend['buckets'], array_keys($trend['buckets'])),
            ])
        </div>
    </section>
</div>
