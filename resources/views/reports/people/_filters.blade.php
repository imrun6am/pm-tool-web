{{-- Period and team, shared by the report and a person's page. --}}
@php
    $isDefault = ! $filters['team_id']
        && $filters['to']->isSameDay(today())
        && $filters['from']->isSameDay(today()->subDays(29));
    $team = array_filter(['team_id' => $filters['team_id']]);
@endphp
<div class="space-y-2">
    <x-filter-bar :action="$action" :active="! $isDefault">
        <label for="from" class="filter-label">From</label>
        <input id="from" type="date" name="from" value="{{ $filters['from']->toDateString() }}" max="{{ today()->toDateString() }}" class="filter-select w-auto">
        <label for="to" class="filter-label">To</label>
        <input id="to" type="date" name="to" value="{{ $filters['to']->toDateString() }}" max="{{ today()->toDateString() }}" class="filter-select w-auto">
        <label for="team_id" class="filter-label">Team</label>
        <select id="team_id" name="team_id" class="filter-select max-w-[14rem]">
            <option value="">All teams</option>
            @foreach ($teams as $option)
                <option value="{{ $option->id }}" @selected($option->id === $filters['team_id'])>{{ $option->name }}{{ $option->isArchived() ? ' (archived)' : '' }}</option>
            @endforeach
        </select>
    </x-filter-bar>

    <nav class="flex flex-wrap items-center gap-1.5" aria-label="Common periods">
        @foreach ($presets as $label => $preset)
            @php $current = $preset['from']->isSameDay($filters['from']) && $preset['to']->isSameDay($filters['to']); @endphp
            <a href="{{ $action }}?{{ http_build_query(['from' => $preset['from']->toDateString(), 'to' => $preset['to']->toDateString()] + $team) }}"
               @if ($current) aria-current="true" @endif
               @class(['rounded-full px-2.5 py-1 text-xs font-medium transition-colors',
                       'bg-brand-50 text-brand-700 ring-1 ring-inset ring-brand-600/20' => $current,
                       'text-slate-500 hover:bg-slate-100 hover:text-slate-800' => ! $current])>{{ $label }}</a>
        @endforeach
    </nav>

    @if ($errors->any())
        <p role="alert" class="text-sm font-medium text-danger-700">{{ $errors->first() }}</p>
    @endif
</div>
