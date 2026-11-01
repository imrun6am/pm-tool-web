{{--
    Reusable KPI stat tile.
    @include('partials.stat-tile', ['label' => 'Projects', 'value' => 12,
             'sub' => 'total', 'icon' => 'folder', 'tone' => 'brand'])
    `sub`, `icon`, `tone` are optional. tone: any hue, meaning or area in
    App\Support\Palette (brand, emerald, success, danger, releases…).
--}}
@php
    $toneClass = \App\Support\Palette::soft($tone ?? 'brand');
@endphp
<div class="card card-pad">
    <div class="flex items-center justify-between gap-2">
        <span class="text-sm font-medium text-slate-500">{{ $label }}</span>
        @isset($icon)
            <span class="flex h-8 w-8 flex-none items-center justify-center rounded-lg ring-1 ring-inset ring-current/10 {{ $toneClass }}">
                <x-icon :name="$icon" class="h-4 w-4" />
            </span>
        @endisset
    </div>
    <p class="tabular mt-2 text-[28px] font-semibold leading-none tracking-tight text-slate-900">{{ $value }}</p>
    @isset($sub)<p class="mt-2 text-xs text-slate-500">{{ $sub }}</p>@endisset
</div>
