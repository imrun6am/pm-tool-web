<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-4">
                <h2 class="page-title">Evaluate</h2>

                @if ($team)
                    {{-- Team switcher --}}
                    <form method="GET" action="{{ route('performance.evaluate') }}">
                        <input type="hidden" name="cadence" value="{{ $cadence }}">
                        <input type="hidden" name="date" value="{{ $period['start']->toDateString() }}">
                        <select name="team" onchange="this.form.submit()" aria-label="Team"
                                class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            @foreach ($teams as $t)
                                <option value="{{ $t->id }}" @selected($team->id === $t->id)>{{ $t->name }}</option>
                            @endforeach
                        </select>
                    </form>

                    {{-- Cadence tabs --}}
                    <div class="inline-flex rounded-lg bg-slate-100 p-0.5">
                        @foreach (\App\Models\PerformanceCompetency::CADENCES as $val => $label)
                            <a href="{{ route('performance.evaluate', ['team' => $team->id, 'cadence' => $val, 'date' => $period['start']->toDateString()]) }}"
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
                           class="btn-secondary btn-sm !px-2" aria-label="Previous {{ $isWeekly ? 'week' : 'day' }}">‹</a>
                        <form method="GET" action="{{ route('performance.evaluate') }}">
                            <input type="hidden" name="team" value="{{ $team->id }}">
                            <input type="hidden" name="cadence" value="{{ $cadence }}">
                            <input type="date" name="date" value="{{ $date->toDateString() }}" onchange="this.form.submit()"
                                   class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        </form>
                        <a href="{{ route('performance.evaluate', ['team' => $team->id, 'cadence' => $cadence, 'date' => $next->toDateString()]) }}"
                           class="btn-secondary btn-sm !px-2" aria-label="Next {{ $isWeekly ? 'week' : 'day' }}">›</a>
                        <a href="{{ route('performance.evaluate', ['team' => $team->id, 'cadence' => $cadence]) }}" class="btn-ghost btn-sm ml-1">Today</a>
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('performance.index', ['team' => $team?->id]) }}" class="btn-secondary btn-sm">Overview</a>
                @can('manage-competencies')
                    <a href="{{ route('performance.competencies.index') }}" class="btn-ghost btn-sm">Competencies</a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container space-y-5">
            @if (! $team)
                <div class="card p-12 text-center text-sm text-slate-500">
                    No teams available to evaluate. You can only evaluate teams you lead.
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
