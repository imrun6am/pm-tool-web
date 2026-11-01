@php
    /*
     * Overlapping avatars for a release's members, with a "+N" overflow.
     *   @include('releases.partials.member-stack', ['members' => $release->members, 'max' => 4])
     */
    $max = $max ?? 4;
    $members = collect($members);
    $extra = $members->count() - $max;
@endphp
@if ($members->isEmpty())
    <span class="text-xs text-slate-400">—</span>
@else
    <div class="flex flex-none items-center" title="{{ $members->pluck('name')->implode(', ') }}">
        <span class="sr-only">{{ $members->count() }} {{ \Illuminate\Support\Str::plural('member', $members->count()) }}: {{ $members->pluck('name')->implode(', ') }}</span>
        <span class="flex -space-x-1.5">
            @foreach ($members->take($max) as $member)
                <x-avatar :user="$member" size="sm" class="ring-2 ring-white" />
            @endforeach
        </span>
        @if ($extra > 0)
            <span class="tabular ml-1.5 text-xs font-medium text-slate-500" aria-hidden="true">+{{ $extra }}</span>
        @endif
    </div>
@endif
