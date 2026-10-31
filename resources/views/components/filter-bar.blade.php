@props(['action', 'active' => false, 'count' => null, 'noun' => 'result'])
{{--
    Compact, auto-applying filter row wrapping a GET form. Selects and dates
    submit on change; the search slot submits on Enter or after a typing pause
    (Alpine `filterBar`, resources/js/app.js). Keep each control's existing
    `name` so controllers are unaffected.
      <x-filter-bar :action="route('x.index')" :active="$hasFilters" :count="$items->total()" noun="note">
          <x-slot name="search"><input type="search" name="search" class="filter-search" …></x-slot>
          <select name="type" class="filter-select">…</select>
      </x-filter-bar>
--}}
<form method="GET" action="{{ $action }}" role="search" x-data="filterBar" @change="onChange($event)"
      {{ $attributes->merge(['class' => 'filter-bar']) }}>
    @isset($search)
        <div class="relative w-full sm:w-64">
            <x-icon name="magnifying-glass" variant="outline" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            {{ $search }}
        </div>
    @endisset

    {{ $slot }}

    {{-- Keyboard / no-JS submit --}}
    <button type="submit" class="sr-only">Apply filters</button>

    @if (! is_null($count) || $active)
        <div class="ml-auto flex items-center gap-3 pl-1 text-sm">
            @if (! is_null($count))
                <span class="tabular whitespace-nowrap text-slate-500">{{ number_format($count) }} {{ \Illuminate\Support\Str::plural($noun, $count) }}</span>
            @endif
            @if ($active)
                <a href="{{ $action }}" class="inline-flex items-center gap-1 whitespace-nowrap rounded-md px-1.5 py-1 font-medium text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900">
                    <x-icon name="x-mark" variant="outline" class="h-4 w-4" />
                    Clear
                </a>
            @endif
        </div>
    @endif
</form>
