@props(['done' => 0, 'total' => 0, 'label' => true, 'size' => 'sm'])
{{--
    A done/total progress bar. Turns success-green at 100%; an empty total
    reads as "No tasks" rather than 0%.
      <x-progress :done="$p['done']" :total="$p['total']" />
--}}
@php
    $pct = $total > 0 ? (int) round($done / $total * 100) : 0;
    $complete = $total > 0 && $done >= $total;
@endphp
<div {{ $attributes->merge(['class' => 'flex min-w-0 items-center gap-2']) }}
     role="img" aria-label="{{ $total > 0 ? "$done of $total tasks done ($pct%)" : 'No tasks yet' }}">
    <div @class(['flex-1 overflow-hidden rounded-full bg-slate-100', 'h-1.5' => $size === 'sm', 'h-2' => $size !== 'sm'])>
        <div @class(['h-full rounded-full transition-[width] duration-300', 'bg-success-500' => $complete, 'bg-brand-500' => ! $complete])
             style="width: {{ $pct }}%"></div>
    </div>
    @if ($label)
        <span @class(['tabular flex-none text-xs font-medium', 'text-success-700' => $complete, 'text-slate-500' => ! $complete])>
            {{ $total > 0 ? "$done/$total" : '—' }}
        </span>
    @endif
</div>
