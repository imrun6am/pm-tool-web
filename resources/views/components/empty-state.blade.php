@props(['icon' => 'squares', 'title', 'description' => null, 'tone' => null])
{{-- Centered empty state for lists. Put actions (buttons/links) in the slot.
     The icon sits on a soft, layered tile in `tone` — any hue, meaning or
     area from App\Support\Palette; by default the current section's hue, so
     an empty Calendar is rose and an empty Notes list is orange. --}}
@php
    $tone ??= \App\Support\Navigation::activeItem()['tone'] ?? 'brand';
    $palette = \App\Support\Palette::class;
@endphp
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-14 text-center']) }}>
    <span class="relative flex h-14 w-14 items-center justify-center" aria-hidden="true">
        <span @class(['absolute inset-0 translate-x-1.5 translate-y-1.5 rotate-6 rounded-2xl', $palette::back($tone)])></span>
        <span @class(['relative flex h-14 w-14 items-center justify-center rounded-2xl shadow-sm ring-1 ring-inset', $palette::soft($tone), $palette::ring($tone)])>
            <x-icon :name="$icon" variant="outline" class="h-7 w-7" />
        </span>
    </span>
    <h3 class="mt-5 text-sm font-semibold text-slate-900">{{ $title }}</h3>
    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-slate-500">{{ $description }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-5 flex flex-wrap items-center justify-center gap-2 text-sm">{{ $slot }}</div>
    @endif
</div>
