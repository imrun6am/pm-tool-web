@props(['label', 'value', 'sub' => null, 'icon' => null, 'tone' => 'slate', 'href' => null, 'alert' => false])
{{--
    One cell of a KPI strip. Wrap cells in <div class="stat-strip">…</div>
    (2-up on phones, 4-up on lg; add lg:grid-cols-3 for three cells).
      <x-stat label="Members" :value="4" sub="on this team" icon="user-group" tone="sky" />
    tone colors the icon; alert switches the whole cell to the warning tone.
--}}
@php
    $tones = [
        'slate' => 'text-slate-500', 'brand' => 'text-brand-600', 'violet' => 'text-violet-600',
        'sky' => 'text-sky-600', 'emerald' => 'text-emerald-600', 'amber' => 'text-amber-600',
        'rose' => 'text-rose-600', 'teal' => 'text-teal-600', 'fuchsia' => 'text-fuchsia-600',
    ];
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif
    {{ $attributes->class(['stat-cell', 'bg-warning-50/50' => $alert]) }}>
    <p @class(['stat-label', 'text-warning-800' => $alert])>
        @if ($icon)
            <x-icon :name="$icon" variant="outline" @class(['h-4 w-4 flex-none', $alert ? 'text-warning-600' : ($tones[$tone] ?? $tones['slate'])]) />
        @endif
        <span class="truncate">{{ $label }}</span>
    </p>
    <p @class(['stat-value', 'text-warning-700' => $alert])>{{ $value }}</p>
    @if ($sub)
        <p @class(['stat-sub', 'text-warning-800' => $alert])>{{ $sub }}</p>
    @endif
    {{ $slot }}
</{{ $tag }}>
