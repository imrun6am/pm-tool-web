@props(['label', 'value', 'sub' => null, 'icon' => null, 'tone' => 'slate', 'href' => null, 'alert' => false])
{{--
    One cell of a KPI strip. Wrap cells in <div class="stat-strip">…</div>
    (2-up on phones, 4-up on lg; add lg:grid-cols-3 for three cells).
      <x-stat label="Members" :value="4" sub="on this team" icon="user-group" tone="sky" />
    tone colors the icon — a hue, a meaning (success, warning, danger, info) or
    an area (releases, calendar…), see App\Support\Palette; alert switches the
    whole cell to the warning tone.
--}}
@php
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif
    {{ $attributes->class(['stat-cell', 'bg-warning-50/50' => $alert]) }}>
    <p @class(['stat-label', 'text-warning-800' => $alert])>
        @if ($icon)
            <x-icon :name="$icon" variant="outline" @class(['h-4 w-4 flex-none', $alert ? 'text-warning-600' : \App\Support\Palette::text($tone)]) />
        @endif
        <span class="truncate">{{ $label }}</span>
    </p>
    <p @class(['stat-value', 'text-warning-700' => $alert])>{{ $value }}</p>
    @if ($sub)
        <p @class(['stat-sub', 'text-warning-800' => $alert])>{{ $sub }}</p>
    @endif
    {{ $slot }}
</{{ $tag }}>
