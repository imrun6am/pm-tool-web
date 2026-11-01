@props(['icon', 'href' => null, 'tone' => 'slate'])
{{--
    Compact 32px icon button for table-row actions. Renders an <a> when :href is
    given, otherwise a form <button>. Always pass a title/aria-label for a11y.
    `tone` maps the action's intent to its hover color through App\Support\Palette:
    danger (delete), success (complete/restore), warning (archive), info/brand (edit).
--}}
@php
    $classes = 'inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition '
        .\App\Support\Palette::hover($tone);
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
