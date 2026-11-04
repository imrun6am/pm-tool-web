{{--
    One dot per item over time, with an optional marker line. Each dot links
    to its item and shows its details on hover.
    @include('partials.scatter-chart', [
        'label' => 'Days to finish each task',
        'from' => $carbon, 'to' => $carbon,
        'points' => [['at' => $carbon, 'value' => 3.5, 'title' => 'PM-12 Fix login', 'note' => '3.5 days · Oct 6', 'href' => '…']],
        'marker' => ['value' => 9.5, 'label' => '85% within 9.5 days'],   // optional
        'unit' => 'days',
    ])
--}}
@php
    $peak = max(1, ceil(max(array_merge([0, $marker['value'] ?? 0], array_column($points, 'value')))));
    $top = \App\Support\ChartScale::top($peak);
    $span = max(1, $from->diffInSeconds($to));
    $left = fn ($at) => round(min(100, max(0, $from->diffInSeconds($at) / $span * 100)), 2);
    $height = fn (float $value) => round(100 - $value / $top * 100, 2);
    $ticks = array_map(fn ($n) => $from->copy()->addSeconds($span * $n / 3), [0, 1, 2, 3]);
@endphp
<div>
    @isset($marker)
        <p class="flex items-center gap-1.5 text-xs text-slate-600"><span class="h-0.5 w-3 rounded-full bg-[#d97706]"></span>{{ $marker['label'] }}</p>
    @endisset
    <div role="group" aria-label="{{ $label }}" class="relative ml-8 mr-1 mt-3 h-52 sm:h-60">
        <svg class="absolute inset-0 h-full w-full overflow-visible" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
            @foreach ([0, 50, 100] as $line)
                <line x1="0" x2="100" y1="{{ $line }}" y2="{{ $line }}" stroke="#e2e8f0" stroke-width="1" vector-effect="non-scaling-stroke" />
            @endforeach
            @isset($marker)
                <line x1="0" x2="100" y1="{{ $height($marker['value']) }}" y2="{{ $height($marker['value']) }}" stroke="#d97706" stroke-width="2" vector-effect="non-scaling-stroke" />
            @endisset
        </svg>

        <div class="tabular absolute inset-y-0 -left-8 w-6 text-right text-[11px] leading-none text-slate-500" aria-hidden="true">
            <span class="absolute right-0 top-0 -translate-y-1/2">{{ $top }}</span>
            @if ($top % 2 === 0)
                <span class="absolute right-0 top-1/2 -translate-y-1/2">{{ $top / 2 }}</span>
            @endif
            <span class="absolute bottom-0 right-0 translate-y-1/2">0</span>
        </div>

        @foreach ($points as $point)
            @php $x = $left($point['at']); $y = $height($point['value']); @endphp
            <a href="{{ $point['href'] }}" aria-label="{{ $point['title'] }}: {{ $point['note'] }}"
               class="group absolute flex h-5 w-5 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full hover:z-20 focus-visible:z-20" style="left: {{ $x }}%; top: {{ $y }}%">
                <span class="h-2 w-2 rounded-full bg-[#0b63f6] ring-2 ring-white transition-transform group-hover:scale-125 group-focus-visible:scale-125"></span>
                <span @class(['pointer-events-none absolute z-20 hidden w-max max-w-64 rounded-lg border border-slate-200 bg-white px-3 py-2 text-left text-xs shadow-md group-hover:block group-focus-visible:block',
                              'right-full mr-1' => $x > 50, 'left-full ml-1' => $x <= 50, 'top-0' => $y <= 50, 'bottom-0' => $y > 50])>
                    <span class="block font-medium text-slate-800">{{ $point['title'] }}</span>
                    <span class="tabular mt-0.5 block text-slate-600">{{ $point['note'] }}</span>
                </span>
            </a>
        @endforeach
    </div>

    <div class="relative ml-8 mr-1 mt-2 h-4 text-[11px] text-slate-500" aria-hidden="true">
        @foreach ($ticks as $n => $tick)
            <span @class(['absolute whitespace-nowrap', '-translate-x-1/2' => $n > 0 && $n < 3, '-translate-x-full' => $n === 3]) style="left: {{ $n / 3 * 100 }}%">{{ $tick->format('M j') }}</span>
        @endforeach
    </div>
</div>
