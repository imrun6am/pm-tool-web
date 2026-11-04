{{--
    Line chart for one to four series over the same x values. The lines stretch
    with the card; labels and marks stay crisp because they're HTML, not SVG.
    @include('partials.line-chart', [
        'label' => 'Scope and finished work',      // accessible name
        'x' => ['Oct 6', 'Oct 13'],
        'series' => [['name' => 'Scope', 'color' => '#d97706', 'values' => [4, 6]]],
        'unit' => 'tasks',                         // for the table and tooltip
    ])
    Direct labels only on the last point; every point shows on hover, and the
    same numbers are in a table underneath.
--}}
@php
    $x = array_values($x);
    $count = count($x);
    $unit ??= '';
    $peak = max(array_merge([1], ...array_map(fn ($s) => $s['values'], $series)));
    $top = \App\Support\ChartScale::top($peak);
    $left = fn (int $i) => $count > 1 ? round($i / ($count - 1) * 100, 2) : 50;
    $height = fn (float $value) => round(100 - $value / $top * 100, 2);
    $every = max(1, (int) ceil($count / 6));

    // Keep end labels apart when the lines finish close together.
    $ends = collect($series)->map(fn ($s, $i) => ['i' => $i, 'name' => $s['name'], 'value' => end($s['values']), 'top' => $height(end($s['values']))])->sortBy('top')->values()->all();
    foreach ($ends as $n => $end) {
        if ($n > 0 && $end['top'] - $ends[$n - 1]['top'] < 10) {
            $ends[$n]['top'] = $ends[$n - 1]['top'] + 10;
        }
    }
@endphp
<div>
    <ul class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-600">
        @foreach ($series as $s)
            <li class="flex items-center gap-1.5"><span class="h-0.5 w-3 rounded-full" style="background: {{ $s['color'] }}"></span>{{ $s['name'] }}</li>
        @endforeach
    </ul>

    <div role="img" aria-label="{{ $label }}" class="relative ml-7 mr-20 mt-3 h-44 sm:h-52">
        <svg class="absolute inset-0 h-full w-full overflow-visible" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
            @foreach ([0, 50, 100] as $line)
                <line x1="0" x2="100" y1="{{ $line }}" y2="{{ $line }}" stroke="#e2e8f0" stroke-width="1" vector-effect="non-scaling-stroke" />
            @endforeach
            @foreach ($series as $s)
                <polyline fill="none" stroke="{{ $s['color'] }}" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"
                          points="{{ collect($s['values'])->map(fn ($v, $i) => $left($i).','.$height($v))->implode(' ') }}" />
            @endforeach
        </svg>

        <div class="tabular absolute inset-y-0 -left-7 w-5 text-right text-[11px] leading-none text-slate-500" aria-hidden="true">
            <span class="absolute right-0 top-0 -translate-y-1/2">{{ $top }}</span>
            @if ($top % 2 === 0)
                <span class="absolute right-0 top-1/2 -translate-y-1/2">{{ $top / 2 }}</span>
            @endif
            <span class="absolute bottom-0 right-0 translate-y-1/2">0</span>
        </div>

        @foreach ($series as $s)
            <span class="absolute h-2 w-2 -translate-x-1/2 -translate-y-1/2 rounded-full ring-2 ring-white" aria-hidden="true"
                  style="left: {{ $left($count - 1) }}%; top: {{ $height(end($s['values'])) }}%; background: {{ $s['color'] }}"></span>
        @endforeach
        @foreach ($ends as $end)
            <span class="tabular absolute left-full ml-2.5 -translate-y-1/2 whitespace-nowrap text-xs text-slate-600" aria-hidden="true" style="top: {{ $end['top'] }}%">
                <span class="font-semibold text-slate-800">{{ $end['value'] }}</span> {{ Str::lower($end['name']) }}
            </span>
        @endforeach

        {{-- Hover: a band per x value shows a crosshair, the marks and the numbers --}}
        @foreach ($x as $i => $name)
            @php $width = 100 / max(1, $count); @endphp
            <div class="group absolute inset-y-0" style="left: calc({{ $left($i) }}% - {{ $width / 2 }}%); width: {{ $width }}%" aria-hidden="true">
                <div class="pointer-events-none absolute inset-y-0 left-1/2 hidden w-px bg-slate-300 group-hover:block"></div>
                @foreach ($series as $s)
                    <span class="pointer-events-none absolute left-1/2 hidden h-2 w-2 -translate-x-1/2 -translate-y-1/2 rounded-full ring-2 ring-white group-hover:block"
                          style="top: {{ $height($s['values'][$i]) }}%; background: {{ $s['color'] }}"></span>
                @endforeach
                <div @class(['pointer-events-none absolute top-0 z-10 hidden min-w-32 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs shadow-md group-hover:block', 'right-1/2 mr-2' => $left($i) > 50, 'left-1/2 ml-2' => $left($i) <= 50])>
                    <p class="whitespace-nowrap font-medium text-slate-800">{{ $name }}</p>
                    @foreach ($series as $s)
                        <p class="mt-1 flex items-center justify-between gap-3 whitespace-nowrap text-slate-600">
                            <span class="flex items-center gap-1.5"><span class="h-0.5 w-3 rounded-full" style="background: {{ $s['color'] }}"></span>{{ $s['name'] }}</span>
                            <span class="tabular font-semibold text-slate-800">{{ $s['values'][$i] }}</span>
                        </p>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <div class="relative ml-7 mr-20 mt-2 h-4 text-[11px] text-slate-500" aria-hidden="true">
        @foreach ($x as $i => $name)
            @if ($i % $every === 0)
                {{-- Every other label on a phone, so they don't run together --}}
                <span @class(['absolute -translate-x-1/2 whitespace-nowrap', 'max-sm:hidden' => $i % ($every * 2) !== 0]) style="left: {{ $left($i) }}%">{{ $name }}</span>
            @endif
        @endforeach
    </div>

    <details class="mt-3 text-xs text-slate-600">
        <summary class="cursor-pointer font-medium text-slate-500 hover:text-slate-700">Show as a table</summary>
        <div class="table-wrap mt-2 max-h-64 overflow-y-auto">
            <table class="table-base">
                <thead><tr><th scope="col">Date</th>@foreach ($series as $s)<th scope="col" class="text-right">{{ $s['name'] }}</th>@endforeach</tr></thead>
                <tbody>
                    @foreach ($x as $i => $name)
                        <tr><th scope="row" class="whitespace-nowrap text-left font-medium">{{ $name }}</th>@foreach ($series as $s)<td class="tabular text-right">{{ $s['values'][$i] }}</td>@endforeach</tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
</div>
