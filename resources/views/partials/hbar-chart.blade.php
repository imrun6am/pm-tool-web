{{--
    Reusable horizontal bar chart (magnitude ranking).
    @include('partials.hbar-chart', [
        'title' => 'Releases by project',
        'subtitle' => 'Active projects, busiest first',   // optional
        'rows' => [['label' => 'Apollo', 'value' => 5, 'color' => '#4f46e5'], ...],
        'barClass' => 'bg-brand-500',   // optional; sequential single hue for magnitude
        'limit' => 8,                    // optional
        'emptyText' => 'No data yet.',   // optional
    ])
    Each row: label, value, and optional color (identity dot beside the label).
--}}
@php
    $rows = $rows ?? [];
    $barClass = $barClass ?? 'bg-brand-500';
    $limit = $limit ?? 8;
    $maxVal = collect($rows)->max('value') ?: 0;
    $shown = array_slice($rows, 0, $limit);
    $moreCount = max(count($rows) - $limit, 0);
@endphp
<div class="card card-pad">
    <h3 class="text-sm font-semibold text-slate-900">{{ $title }}</h3>
    @isset($subtitle)<p class="mt-0.5 text-xs text-slate-400">{{ $subtitle }}</p>@endisset

    @if (empty($rows))
        <p class="mt-6 text-sm text-slate-400">{{ $emptyText ?? 'No data yet.' }}</p>
    @else
        <ul class="mt-5 space-y-3">
            @foreach ($shown as $row)
                <li class="flex items-center gap-3">
                    @if (! empty($row['color']))
                        <span class="h-2.5 w-2.5 flex-none rounded-full" style="background-color: {{ $row['color'] }}"></span>
                    @endif
                    <span class="w-28 flex-none truncate text-sm text-slate-700" title="{{ $row['label'] }}">{{ $row['label'] }}</span>
                    <div class="relative h-2.5 flex-1 overflow-hidden rounded-full bg-slate-100">
                        <div class="absolute inset-y-0 left-0 rounded-full {{ $barClass }}"
                             style="width: {{ $maxVal > 0 ? max((int) round($row['value'] / $maxVal * 100), $row['value'] > 0 ? 4 : 0) : 0 }}%"></div>
                    </div>
                    <span class="w-7 flex-none text-right text-sm font-semibold text-slate-900 tabular">{{ $row['value'] }}</span>
                </li>
            @endforeach
        </ul>
        @if ($moreCount > 0)
            <p class="mt-3 text-xs text-slate-400">+{{ $moreCount }} more</p>
        @endif
    @endif
</div>
