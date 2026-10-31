@props(['icon', 'href' => null, 'tone' => 'slate'])
{{--
    Compact 32px icon button for table-row actions. Renders an <a> when :href is
    given, otherwise a form <button>. Always pass a title/aria-label for a11y.
    Tones map an action's intent to its hover color.
--}}
@php
    $tones = [
        'slate'   => 'hover:bg-slate-100 hover:text-slate-700',
        'brand'   => 'hover:bg-brand-50 hover:text-brand-600',
        'emerald' => 'hover:bg-emerald-50 hover:text-emerald-600',
        'amber'   => 'hover:bg-amber-50 hover:text-amber-600',
        'rose'    => 'hover:bg-rose-50 hover:text-rose-600',
    ];
    $classes = 'inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition '
        .($tones[$tone] ?? $tones['slate']);
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        <x-icon :name="$icon" class="h-4 w-4" />
    </a>
@else
    <button {{ $attributes->merge(['class' => $classes, 'type' => 'submit']) }}>
        <x-icon :name="$icon" class="h-4 w-4" />
    </button>
@endif
