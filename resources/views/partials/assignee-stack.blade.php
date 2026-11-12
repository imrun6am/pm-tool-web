{{-- A task's assignees as overlapping avatars, lead first. Requires $people (a collection of users,
     usually $task->assigneeList()). Optional: $size (xs or sm, default xs) and $names ('first' shows
     the lead's name and "+N", 'all' lists every name, 'none' shows avatars only). --}}
@php
    $size = $size ?? 'xs';
    $names = $names ?? 'first';
    $shown = $people->take(3);
    $more = $people->count() - $shown->count();
    $everyone = $people->pluck('name')->implode(', ');
@endphp
<span class="inline-flex min-w-0 items-center gap-1.5" title="{{ $everyone }}">
    <span class="flex flex-none -space-x-1.5">
        @foreach ($shown as $person)
            <x-avatar :user="$person" :size="$size" class="ring-2 ring-white" />
        @endforeach
        @if ($more > 0)
            <span class="inline-flex {{ $size === 'sm' ? 'h-6 w-6 text-[10px]' : 'h-5 w-5 text-[9px]' }} flex-none items-center justify-center rounded-full bg-slate-100 font-semibold text-slate-600 ring-2 ring-white" aria-hidden="true">+{{ $more }}</span>
        @endif
    </span>
    @if ($names === 'all')
        <span class="truncate">{{ $everyone }}</span>
    @elseif ($names === 'first')
        <span class="truncate">{{ $people->first()?->name }}@if ($people->count() > 1)<span class="text-slate-400"> +{{ $people->count() - 1 }}</span>@endif</span>
        @if ($people->count() > 1)<span class="sr-only">Assigned to {{ $everyone }}</span>@endif
    @else
        <span class="sr-only">Assigned to {{ $everyone }}</span>
    @endif
</span>
