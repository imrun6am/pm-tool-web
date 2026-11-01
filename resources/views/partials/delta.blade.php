{{--
    Change-vs-previous pill for a KPI, framed to encourage: an improvement is
    green, a dip stays neutral slate (never red), and no comparison renders
    nothing at all.
    @include('partials.delta', ['change' => 2, 'unit' => '', 'versus' => 'last month', 'lowerIsBetter' => false])
    `unit` is appended to the number (e.g. ' pts', 'd').
--}}
@php
    $change = $change ?? null;
    $unit = $unit ?? '';
    $versus = $versus ?? 'last period';
    $lowerIsBetter = $lowerIsBetter ?? false;
    $better = $change !== null && ($lowerIsBetter ? $change < 0 : $change > 0);
    $fmt = fn ($n) => rtrim(rtrim(number_format(abs($n), 1), '0'), '.');
@endphp
@if ($change !== null)
    @if ($change == 0)
        <span class="inline-flex items-center gap-1 text-xs font-medium text-slate-500">Same as {{ $versus }}</span>
    @else
        <span @class([
            'inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 text-xs font-medium',
            'bg-success-50 text-success-700' => $better,
            'bg-slate-100 text-slate-600' => ! $better,
        ])>
            <span aria-hidden="true">{{ $change > 0 ? '▲' : '▼' }}</span>
            <span class="sr-only">{{ $change > 0 ? 'Up' : 'Down' }}</span>
            <span class="tabular">{{ $fmt($change) }}{{ $unit }}</span>
            <span class="font-normal opacity-80">vs {{ $versus }}</span>
        </span>
    @endif
@endif
