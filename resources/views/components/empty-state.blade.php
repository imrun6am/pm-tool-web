@props(['icon' => 'squares', 'title', 'description' => null])
{{-- Centered empty state for lists. Put actions (buttons/links) in the slot. --}}
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-14 text-center']) }}>
    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-white text-slate-400 shadow-sm ring-1 ring-inset ring-slate-200">
        <x-icon :name="$icon" variant="outline" class="h-6 w-6" />
    </span>
    <h3 class="mt-4 text-sm font-semibold text-slate-900">{{ $title }}</h3>
    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-slate-500">{{ $description }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-5 flex flex-wrap items-center justify-center gap-2 text-sm">{{ $slot }}</div>
    @endif
</div>
