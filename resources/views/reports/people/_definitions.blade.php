{{-- What each figure in the current group means, in the open rather than only on hover. --}}
@php use App\Support\PeopleReportColumns as Columns; @endphp
<details class="card group">
    <summary class="cursor-pointer list-none px-5 py-3 text-sm font-medium text-slate-700 hover:text-slate-900">How these figures are counted</summary>
    <div class="space-y-3 border-t border-slate-100 px-5 py-4 text-sm text-slate-600">
        @foreach (array_keys(Columns::GROUPS) as $group)
            <dl class="grid gap-x-6 gap-y-1.5 sm:grid-cols-[12rem_1fr]" x-show="group === '{{ $group }}'" @unless ($loop->first) x-cloak @endunless>
                @foreach (Columns::visible($group) as $column)
                    @isset($column['hint'])
                        <dt class="font-medium text-slate-700">{{ $column['label'] }}</dt>
                        <dd>{{ $column['hint'] }}</dd>
                    @endisset
                @endforeach
            </dl>
        @endforeach
        <p>"Too few" means there's not enough behind a rate to quote it: under {{ \App\Services\PeopleAnalyticsService::MIN_TASKS }} tasks, {{ \App\Services\PeopleAnalyticsService::MIN_DAYS }} days, or half the competencies that apply. The number in brackets is how many there were. Archived tasks never count.</p>
        <p>Working days follow the team calendar: {{ \App\Support\WorkingWeek::weekendLabel() ?: 'no weekend' }} off. No figure here is combined into a score, and time logged doesn't feed ratings or KPI reviews.</p>
    </div>
</details>
