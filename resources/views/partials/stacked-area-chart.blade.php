{{--
    Stacked bands over time, first band at the bottom. Hovering a column shows
    its numbers; the same numbers are in a table underneath.
    @include('partials.stacked-area-chart', [
        'label' => 'Tasks in each status by week',
        'x' => ['Oct 6', 'Oct 13'],
        'bands' => [['name' => 'Done', 'color' => '#10b981', 'values' => [1, 3]]],
    ])
--}}
@php
    $x = array_values($x);
    $count = count($x);
    $totals = array_map(fn ($i) => array_sum(array_map(fn ($b) => $b['values'][$i], $bands)), array_keys($x));
    $peak = max(1, ...$totals);
    $top = \App\Support\ChartScale::top($peak);
    $left = fn (int $i) => $count > 1 ? round($i / ($count - 1) * 100, 2) : 50;
    $height = fn (float $value) => round(100 - $value / $top * 100, 2);
    $every = max(1, (int) ceil($count / 6));

    $floor = array_fill(0, $count, 0);
    $shapes = [];
    foreach ($bands as $band) {
        $ceiling = array_map(fn ($i) => $floor[$i] + $band['values'][$i], array_keys($x));
        $upper = array_map(fn ($i) => $left($i).','.$height($ceiling[$i]), array_keys($x));
        $lower = array_map(fn ($i) => $left($i).','.$height($floor[$i]), array_reverse(array_keys($x)));
        $shapes[] = ['color' => $band['color'], 'points' => implode(' ', array_merge($upper, $lower))];
        $floor = $ceiling;
    }
    $reversed = array_reverse($bands);
@endphp
<div>
    <ul class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-600">
        @foreach ($reversed as $band)
            <li class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm" style="background: {{ $band['color'] }}"></span>{{ $band['name'] }}</li>
        @endforeach
    </ul>

    <div role="img" aria-label="{{ $label }}" class="relative ml-8 mt-3 h-56 sm:h-64">
        <svg class="absolute inset-0 h-full w-full overflow-visible" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
            @foreach ([0, 50, 100] as $line)
                <line x1="0" x2="100" y1="{{ $line }}" y2="{{ $line }}" stroke="#e2e8f0" stroke-width="1" vector-effect="non-scaling-stroke" />
            @endforeach
            @foreach ($shapes as $shape)
                <polygon fill="{{ $shape['color'] }}" stroke="#ffffff" stroke-width="2" stroke-linejoin="round" vector-effect="non-scaling-stroke" points="{{ $shape['points'] }}" />
            @endforeach
        </svg>

        <div class="tabular absolute inset-y-0 -left-8 w-6 text-right text-[11px] leading-none text-slate-500" aria-hidden="true">
            <span class="absolute right-0 top-0 -translate-y-1/2">{{ $top }}</span>
            @if ($top % 2 === 0)
                <span class="absolute right-0 top-1/2 -translate-y-1/2">{{ $top / 2 }}</span>
            @endif
            <span class="absolute bottom-0 right-0 translate-y-1/2">0</span>
        </div>

        @foreach ($x as $i => $name)
            @php $width = 100 / max(1, $count); @endphp
            <div class="group absolute inset-y-0" style="left: calc({{ $left($i) }}% - {{ $width / 2 }}%); width: {{ $width }}%" aria-hidden="true">
                <div class="pointer-events-none absolute inset-y-0 left-1/2 hidden w-px bg-slate-900/40 group-hover:block"></div>
                <div @class(['pointer-events-none absolute top-1 z-10 hidden min-w-36 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs shadow-md group-hover:block', 'right-1/2 mr-2' => $left($i) > 50, 'left-1/2 ml-2' => $left($i) <= 50])>
                    <p class="whitespace-nowrap font-medium text-slate-800">{{ $name }}</p>
                    @foreach ($reversed as $band)
                        <p class="mt-1 flex items-center justify-between gap-3 whitespace-nowrap text-slate-600">
                            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-sm" style="background: {{ $band['color'] }}"></span>{{ $band['name'] }}</span>
                            <span class="tabular font-semibold text-slate-800">{{ $band['values'][$i] }}</span>
                        </p>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <div class="relative ml-8 mt-2 h-4 text-[11px] text-slate-500" aria-hidden="true">
        @foreach ($x as $i => $name)
            @if ($i % $every === 0)
                {{-- Every other label on a phone, so they don't run together --}}
                <span @class(['absolute whitespace-nowrap', '-translate-x-1/2' => $i > 0, 'max-sm:hidden' => $i % ($every * 2) !== 0]) style="left: {{ $left($i) }}%">{{ $name }}</span>
            @endif
        @endforeach
    </div>

    <details class="mt-3 text-xs text-slate-600">
        <summary class="cursor-pointer font-medium text-slate-500 hover:text-slate-700">Show as a table</summary>
        <div class="table-wrap mt-2 max-h-64 overflow-y-auto">
            <table class="table-base">
                <thead><tr><th scope="col">Week</th>@foreach ($reversed as $band)<th scope="col" class="text-right">{{ $band['name'] }}</th>@endforeach</tr></thead>
                <tbody>
                    @foreach ($x as $i => $name)
                        <tr><th scope="row" class="whitespace-nowrap text-left font-medium">{{ $name }}</th>@foreach ($reversed as $band)<td class="tabular text-right">{{ $band['values'][$i] }}</td>@endforeach</tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
</div>
