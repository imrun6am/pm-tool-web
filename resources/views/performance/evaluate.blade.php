<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Evaluate" description="Rate each developer and QA on the team's competencies for the period."
                       :breadcrumbs="['Performance' => route('performance.index', ['team' => $team?->id]), 'Evaluate' => null]">
            <x-slot name="actions">
                @can('manage-competencies')
                    <a href="{{ route('performance.competencies.index') }}" class="btn-ghost btn-sm">Competencies</a>
                @endcan
                <a href="{{ route('performance.index', ['team' => $team?->id]) }}" class="btn-secondary btn-sm">Overview</a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container space-y-5">
            @if ($team)
                {{-- One GET form: changing the team or the date reloads the grid
                     for the same cadence. Cadence tabs and prev/next stay links. --}}
                <x-filter-bar :action="route('performance.evaluate')">
                    <input type="hidden" name="cadence" value="{{ $cadence }}">

                    <label for="team" class="filter-label">Team</label>
                    <select id="team" name="team" class="filter-select max-w-[14rem]">
                        @foreach ($teams as $t)
                            <option value="{{ $t->id }}" @selected($team->id === $t->id)>{{ $t->name }}</option>
                        @endforeach
                    </select>

                    {{-- Cadence tabs --}}
                    <div class="inline-flex h-9 items-center rounded-lg bg-slate-100 p-0.5">
                        @foreach (\App\Models\PerformanceCompetency::CADENCES as $val => $label)
                            <a href="{{ route('performance.evaluate', ['team' => $team->id, 'cadence' => $val, 'date' => $period['start']->toDateString()]) }}"
                               @if ($cadence === $val) aria-current="page" @endif
                               @class([
                                   'rounded-md px-3 py-1.5 text-sm font-medium transition',
                                   'bg-white text-slate-900 shadow-sm' => $cadence === $val,
                                   'text-slate-500 hover:text-slate-700' => $cadence !== $val,
                               ])>{{ $label }}</a>
                        @endforeach
                    </div>

                    {{-- Period navigation --}}
                    <div class="flex items-center gap-1">
                        <a href="{{ route('performance.evaluate', ['team' => $team->id, 'cadence' => $cadence, 'date' => $prev->toDateString()]) }}"
                           class="btn-secondary btn-sm btn-icon" aria-label="Previous {{ $isWeekly ? 'week' : 'day' }}">‹</a>
                        <label for="date" class="filter-label">{{ $isWeekly ? 'Week of' : 'Day' }}</label>
                        <input id="date" type="date" name="date" value="{{ $date->toDateString() }}" class="filter-date">
                        <a href="{{ route('performance.evaluate', ['team' => $team->id, 'cadence' => $cadence, 'date' => $next->toDateString()]) }}"
                           class="btn-secondary btn-sm btn-icon" aria-label="Next {{ $isWeekly ? 'week' : 'day' }}">›</a>
                        <a href="{{ route('performance.evaluate', ['team' => $team->id, 'cadence' => $cadence]) }}" class="btn-ghost btn-sm">Today</a>
                    </div>
                </x-filter-bar>
            @endif

            @if (! $team)
                <div class="card">
                    <x-empty-state icon="chart-bar" title="No teams available to evaluate."
                                   description="You can only evaluate teams you lead." />
                </div>
            @else
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">{{ $team->name }}</h3>
                        <p class="text-xs text-slate-400">{{ $isWeekly ? 'Weekly' : 'Daily' }} competencies · {{ $periodLabel }}</p>
                    </div>
                    @if ($competencies->isNotEmpty())
                        <p class="text-xs text-slate-400">
                            Rate each member 1–5. {{ $competencies->count() }} {{ $isWeekly ? 'weekly' : 'daily' }} {{ \Illuminate\Support\Str::plural('competency', $competencies->count()) }}.
                        </p>
                    @endif
                </div>

                @if ($isFuture)
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        This {{ $isWeekly ? 'week' : 'day' }} hasn’t started yet — you can’t record ratings for a future period.
                    </div>
                @endif

                @if ($competencies->isEmpty())
                    <div class="card p-12 text-center text-sm text-slate-500">
                        No active {{ $isWeekly ? 'weekly' : 'daily' }} competencies.
                        @can('manage-competencies')
                            <a href="{{ route('performance.competencies.index') }}" class="font-medium text-brand-600">Manage the catalog →</a>
                        @endcan
                    </div>
                @elseif ($rowUsers->isEmpty())
                    <div class="card p-12 text-center text-sm text-slate-500">
                        No developers or QA on this team to evaluate.
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach ($rowUsers as $rowUser)
                            @include('performance._eval_member', ['rowUser' => $rowUser, 'locked' => $isFuture])
                        @endforeach
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
