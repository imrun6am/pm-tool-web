@props(['title', 'description' => null, 'breadcrumbs' => []])
{{--
    Standard page header for the `header` slot.
    breadcrumbs: label => url pairs; the last crumb is the current page (url may be null).
    <x-page-header title="New project" :breadcrumbs="['Projects' => route('projects.index'), 'New project' => null]">
        <x-slot name="actions">…buttons…</x-slot>
    </x-page-header>
--}}
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-end justify-between gap-x-6 gap-y-3']) }}>
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
    @isset($actions)
        <div class="flex flex-none flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
