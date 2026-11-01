{{--
    Single-series vertical bar chart (CSS bars, no JS).
    @include('partials.column-chart', [
        'bars' => [['label' => 'Oct 6', 'value' => 3, 'current' => false, 'highlight' => false, 'title' => '3 tasks · week of Oct 6'], ...],
        'label' => 'Tasks completed per week',   // aria-label
        'unit' => 'task',                         // optional, for the tooltip
    ])
    Only the current and highlighted bars carry a value label; every bar has a
    hover title. Bars rise from a shared baseline with 4px rounded tops.
    A bar may carry an `href` (drill-down): bars then render as labelled links
    inside a group, instead of one decorative image.
--}}
@php
    $bars = array_values($bars ?? []);
    $max = max(array_column($bars, 'value') ?: [0]);
    $scale = max($max, 1);
    $summary = collect($bars)->map(fn ($b) => $b['label'].': '.$b['value'])->implode(', ');
    $linked = collect($bars)->contains(fn ($b) => ! empty($b['href']));
@endphp
<div @if ($linked) role="group" aria-label="{{ $label ?? 'Bar chart' }}" @else role="img" aria-label="{{ $label ?? 'Bar chart' }}. {{ $summary }}" @endif>
    <div class="relative mt-5 flex h-36 items-end gap-1.5 border-b border-slate-200 sm:gap-2" @unless ($linked) aria-hidden="true" @endunless>
        {{-- recessive gridlines at half and full scale --}}
        <div class="pointer-events-none absolute inset-x-0 top-0 border-t border-dashed border-slate-100"></div>
        <div class="pointer-events-none absolute inset-x-0 top-1/2 border-t border-dashed border-slate-100"></div>
        @foreach ($bars as $bar)
            @php $h = $bar['value'] > 0 ? max(4, round($bar['value'] / $scale * 100)) : 0; @endphp
            @php $tag = ! empty($bar['href']) ? 'a' : 'div'; @endphp
            <{{ $tag }} @if ($tag === 'a') href="{{ $bar['href'] }}" aria-label="{{ $bar['title'] ?? $bar['label'].': '.$bar['value'] }}" @endif
                class="group relative flex h-full flex-1 items-end" title="{{ $bar['title'] ?? $bar['label'].': '.$bar['value'] }}">
                <div @class([
                    'relative w-full rounded-t transition-colors',
                    'bg-brand-600' => $bar['current'] ?? false,
                    'bg-brand-400 group-hover:bg-brand-500' => ($bar['highlight'] ?? false) && ! ($bar['current'] ?? false),
                    'bg-brand-200 group-hover:bg-brand-300' => ! ($bar['current'] ?? false) && ! ($bar['highlight'] ?? false),
                ]) style="height: {{ $h }}%">
                    @if ($bar['value'] > 0 && (($bar['current'] ?? false) || ($bar['highlight'] ?? false)))
                        {{-- label floats above the bar so it never steals bar height --}}
                        <span class="tabular absolute inset-x-0 bottom-full mb-1 text-center text-[11px] font-semibold text-slate-700">{{ $bar['value'] }}</span>
                    @endif
                </div>
            </{{ $tag }}>
        @endforeach
    </div>
    <div class="mt-1.5 flex gap-1.5 sm:gap-2" aria-hidden="true">
        @foreach ($bars as $i => $bar)
            <span @class([
                'flex-1 truncate text-center text-[10px] tabular',
                'font-semibold text-slate-700' => $bar['current'] ?? false,
                'text-slate-400' => ! ($bar['current'] ?? false),
                'max-sm:invisible' => $i % 2 === 1 && ! ($bar['current'] ?? false),
            ])>{{ $bar['label'] }}</span>
        @endforeach
    </div>
</div>
