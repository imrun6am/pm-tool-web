{{--
    Reusable KPI stat tile.
    @include('partials.stat-tile', ['label' => 'Projects', 'value' => 12,
             'sub' => 'total', 'icon' => 'folder', 'tone' => 'brand'])
    `sub`, `icon`, `tone` are optional. tone ∈ brand|emerald|amber|sky|slate|rose
--}}
@php
    $tone = $tone ?? 'brand';
    $palette = [
        'brand' => 'bg-brand-50 text-brand-600',
        'emerald' => 'bg-emerald-50 text-emerald-600',
        'amber' => 'bg-amber-50 text-amber-600',
        'sky' => 'bg-sky-50 text-sky-600',
        'slate' => 'bg-slate-100 text-slate-600',
        'rose' => 'bg-rose-50 text-rose-600',
    ];
    $toneClass = $palette[$tone] ?? $palette['brand'];
@endphp
<div class="card card-pad">
    <div class="flex items-center justify-between gap-2">
        <span class="text-xs font-medium text-slate-500">{{ $label }}</span>
        @isset($icon)
            <span class="flex h-8 w-8 flex-none items-center justify-center rounded-lg {{ $toneClass }}">
                <x-icon :name="$icon" class="h-4 w-4" />
            </span>
        @endisset
    </div>
    <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">{{ $value }}</p>
    @isset($sub)<p class="mt-1 text-xs text-slate-400">{{ $sub }}</p>@endisset
</div>
