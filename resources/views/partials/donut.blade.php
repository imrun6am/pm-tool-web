{{--
    Inline-SVG donut with a counted legend (the legend is the direct label, so
    identity never rests on color alone).
    @include('partials.donut', [
        'segments' => [['label' => 'To Do', 'value' => 4, 'color' => '#94a3b8'], ...],
        'center' => '60%', 'caption' => 'done', 'label' => 'My tasks by status',
    ])
    Uses r=15.9155 so the circumference is 100 and dash lengths are percentages.
    A segment may carry an `href`: its legend row becomes a drill-down link.
--}}
@php
    $segments = array_values($segments ?? []);
    $total = array_sum(array_column($segments, 'value'));
    $summary = collect($segments)->map(fn ($s) => $s['label'].': '.$s['value'])->implode(', ');
    $cursor = 0;
    // A 0.6-unit gap between segments (the 2px surface spacer), unless one fills the ring.
    $gap = collect($segments)->where('value', '>', 0)->count() > 1 ? 0.6 : 0;
@endphp
<div class="flex flex-wrap items-center justify-center gap-x-6 gap-y-4">
    <div class="relative h-32 w-32 flex-none">
        <svg viewBox="0 0 42 42" class="h-full w-full -rotate-90" role="img" aria-label="{{ $label ?? 'Breakdown' }}. {{ $summary }}">
            <circle cx="21" cy="21" r="15.9155" fill="none" stroke="#f1f5f9" stroke-width="5" />
            @foreach ($segments as $segment)
                @if ($segment['value'] > 0 && $total > 0)
                    @php $pct = $segment['value'] / $total * 100; @endphp
                    <circle cx="21" cy="21" r="15.9155" fill="none" stroke-width="5" stroke="{{ $segment['color'] }}"
                            stroke-dasharray="{{ round(max($pct - $gap, 0.1), 3) }} {{ round(100 - max($pct - $gap, 0.1), 3) }}"
                            stroke-dashoffset="{{ round(-$cursor, 3) }}"><title>{{ $segment['label'] }}: {{ $segment['value'] }}</title></circle>
                    @php $cursor += $pct; @endphp
                @endif
            @endforeach
        </svg>
        <div class="absolute inset-0 flex flex-col items-center justify-center">
            <span class="tabular text-2xl font-semibold leading-none tracking-tight text-slate-900">{{ $center ?? $total }}</span>
            @isset($caption)<span class="mt-1 text-[11px] text-slate-500">{{ $caption }}</span>@endisset
        </div>
    </div>
    <ul class="min-w-[9rem] flex-1 space-y-1.5">
        @foreach ($segments as $segment)
            <li>
                {{-- a segment with an `href` makes its legend row a drill-down link --}}
                <{{ ! empty($segment['href']) ? 'a' : 'span' }} @if (! empty($segment['href'])) href="{{ $segment['href'] }}" @endif
                    @class(['flex items-center gap-2 text-sm', 'rounded -mx-1 px-1 hover:bg-slate-50' => ! empty($segment['href'])])>
                    <span class="h-2.5 w-2.5 flex-none rounded-full" style="background-color: {{ $segment['color'] }}"></span>
                    <span class="truncate text-slate-600">{{ $segment['label'] }}</span>
                    <span class="tabular ml-auto font-semibold text-slate-900">{{ $segment['value'] }}</span>
                </{{ ! empty($segment['href']) ? 'a' : 'span' }}>
            </li>
        @endforeach
    </ul>
</div>
