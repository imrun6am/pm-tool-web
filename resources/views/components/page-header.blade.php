@props(['title', 'description' => null, 'breadcrumbs' => [], 'icon' => null, 'tone' => null])
{{--
    Standard page header for the `header` slot.
    breadcrumbs: label => url pairs; the last crumb is the current page (url may be null).
    icon/tone: the section's icon tile, in its area's hue (App\Support\Palette).
    By default both come from the menu item the current route belongs to;
    pass icon="bell" tone="notifications" for pages outside the menu, or
    :icon="false" for none.
    <x-page-header title="New project" :breadcrumbs="['Projects' => route('projects.index'), 'New project' => null]">
        <x-slot name="actions">…buttons…</x-slot>
    </x-page-header>
--}}
@php
    $section = $icon === null ? \App\Support\Navigation::activeItem() : null;
    $tileIcon = $icon ?? $section['icon'] ?? null;
    $tileTone = $tone ?? $section['tone'] ?? 'brand';
@endphp
{{-- A faint glow in the section's hue behind the header band (.app-page-header). --}}
<span class="page-glow-clip" aria-hidden="true"><span @class(['page-glow', \App\Support\Palette::back($tileTone)])></span></span>
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-end justify-between gap-x-6 gap-y-3']) }}>
    <div class="flex min-w-0 items-center gap-3.5">
    @if ($tileIcon)
        <span @class(['hidden h-11 w-11 flex-none items-center justify-center rounded-xl shadow-sm ring-1 ring-inset sm:flex', \App\Support\Palette::soft($tileTone), \App\Support\Palette::ring($tileTone)]) aria-hidden="true">
            <x-icon :name="$tileIcon" variant="outline" class="h-6 w-6" />
        </span>
    @endif
    <div class="min-w-0">
        @if ($breadcrumbs)
            <nav aria-label="Breadcrumb" class="mb-1">
                <ol class="flex flex-wrap items-center gap-1 text-[13px] text-slate-500">
                    @foreach ($breadcrumbs as $label => $url)
                        <li class="flex items-center gap-1">
                            @if ($loop->last)
                                <span aria-current="page" class="font-medium text-slate-600">{{ $label }}</span>
                            @else
                                <a href="{{ $url }}" class="rounded transition-colors hover:text-brand-700">{{ $label }}</a>
                                <x-icon name="chevron-right" variant="outline" class="h-3.5 w-3.5 flex-none text-slate-400" />
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif
        <h1 class="page-title truncate">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1 max-w-2xl text-sm text-slate-500">{{ $description }}</p>
        @endif
    </div>
    </div>
    @isset($actions)
        <div class="flex min-w-0 flex-wrap items-center gap-2 sm:flex-none">{{ $actions }}</div>
    @endisset
</div>
