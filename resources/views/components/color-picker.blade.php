@props(['name' => 'color', 'value' => null, 'id' => null])
{{-- Preset swatches + a native custom color. Submits `name` as a #rrggbb hex. --}}
@php
    $value = strtolower($value ?: '#4f46e5');
    $id = $id ?? $name;
    $presets = ['#4f46e5', '#7c3aed', '#c026d3', '#db2777', '#e11d48', '#ea580c', '#d97706', '#16a34a', '#0d9488', '#0891b2', '#2563eb', '#475569'];
@endphp
<div x-data="{ color: @js($value) }" class="mt-1.5 flex flex-wrap items-center gap-2">
    @foreach ($presets as $hex)
        <button type="button" @click="color = '{{ $hex }}'"
                class="h-7 w-7 rounded-full ring-offset-2 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                :class="color === '{{ $hex }}' ? 'ring-2 ring-slate-800' : 'hover:scale-110'"
                style="background-color: {{ $hex }}"
                aria-label="Use color {{ $hex }}" :aria-pressed="(color === '{{ $hex }}').toString()"></button>
    @endforeach
    <label class="ml-1 inline-flex h-8 cursor-pointer items-center gap-2 rounded-lg border border-slate-300 bg-white pl-1 pr-2.5 text-xs font-medium text-slate-600 shadow-sm transition hover:border-slate-400"
           title="Custom color">
        <input id="{{ $id }}" name="{{ $name }}" type="color" value="{{ $value }}" x-model="color"
               {{ $attributes->merge(['class' => 'h-6 w-6 cursor-pointer rounded-md border-0 bg-transparent p-0 [&::-moz-color-swatch]:rounded-md [&::-webkit-color-swatch-wrapper]:p-0 [&::-webkit-color-swatch]:rounded-md [&::-webkit-color-swatch]:border-0']) }}>
        <span class="tabular font-mono uppercase" x-text="color">{{ $value }}</span>
    </label>
</div>
