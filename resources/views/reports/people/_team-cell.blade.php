<div class="flex min-w-0 items-center gap-2.5">
    <span class="h-2.5 w-2.5 flex-none rounded-full" @if ($row['color']) style="background-color: {{ $row['color'] }}" @else style="background-color: #cbd5e1" @endif aria-hidden="true"></span>
    <div class="min-w-0">
        @if ($row['id'])
            <a href="{{ route('reports.people', ['team_id' => $row['id']] + $periodQuery) }}" class="block truncate text-sm font-medium text-slate-800 hover:text-brand-700">{{ $row['name'] }}@if ($row['archived']) <span class="font-normal text-slate-500">(archived)</span>@endif</a>
        @else
            <span class="block truncate text-sm font-medium text-slate-800">{{ $row['name'] }}</span>
        @endif
        <span class="block truncate text-xs text-slate-500">
            @if ($row['id'])
                {{ $row['people'] }} {{ Str::plural('person', $row['people']) }}@if ($row['lead']), led by {{ $row['lead']->name }}@endif
            @else
                Work with no team to belong to
            @endif
        </span>
    </div>
</div>
