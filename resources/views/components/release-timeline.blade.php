@props(['releases', 'conflicts' => [], 'label' => 'team'])
{{--
    A compact Gantt of releases: one row per release, its window drawn as a bar
    across the span of all of them, with month ticks and a "today" marker.
    Bars take the project's color when label="team" rows are listed by name,
    or the team's color when label="project"; a double-booked release
    (`$conflicts[id]` true, see OverlapChecker::flagConflicts) gets a warning ring.
      <x-release-timeline :releases="$releases" :conflicts="$conflicts" label="project" />
    Expects each release with project and team loaded.
--}}
@php
    use Illuminate\Support\Carbon;

    $releases = collect($releases)->sortBy('start_date')->values();
    $from = $releases->min('start_date')?->copy()->startOfMonth();
    $to = $releases->max('end_date')?->copy()->endOfMonth();
    $span = $from ? max($from->diffInDays($to) + 1, 1) : 1;
    $pos = fn (Carbon $d) => round($from->diffInDays($d) / $span * 100, 3);

    $months = [];
    if ($from) {
        for ($m = $from->copy(); $m->lte($to); $m->addMonth()) {
            $months[] = ['label' => $m->format($m->month === 1 || empty($months) ? 'M Y' : 'M'), 'left' => $pos($m)];
        }
    }
    $todayLeft = $from && today()->between($from, $to) ? $pos(today()) : null;
@endphp

@if ($releases->isEmpty())
    <x-empty-state icon="rocket" title="No releases yet." {{ $attributes }} />
@else
    <div {{ $attributes->merge(['class' => 'overflow-x-auto']) }}>
        <div class="min-w-[36rem]">
            {{-- Month axis --}}
            <div class="relative ml-40 h-6 border-b border-slate-100 text-[11px] font-medium text-slate-400 sm:ml-48">
                @foreach ($months as $month)
                    <span class="absolute top-1 -translate-x-px whitespace-nowrap border-l border-slate-200 pl-1" style="left: {{ $month['left'] }}%">{{ $month['label'] }}</span>
                @endforeach
            </div>

            <ul class="relative divide-y divide-slate-50">
                @foreach ($releases as $release)
                    @php
                        $left = $pos($release->start_date);
                        $width = max(round(($release->start_date->diffInDays($release->end_date) + 1) / $span * 100, 3), 1.2);
                        $barColor = $label === 'project' ? $release->team->color : $release->project->color;
                        $conflict = $conflicts[$release->id] ?? false;
                    @endphp
                    <li class="group flex items-center gap-3 py-1.5">
                        <a href="{{ route('releases.show', $release) }}"
                           class="w-40 flex-none truncate text-sm font-medium text-slate-700 hover:text-brand-700 sm:w-48"
                           title="{{ $release->name }}">{{ $release->name }}</a>
                        <div class="relative h-6 flex-1">
                            @if ($todayLeft !== null)
                                <span class="absolute inset-y-0 w-px bg-danger-400/70" style="left: {{ $todayLeft }}%" aria-hidden="true"></span>
                            @endif
                            <a href="{{ route('releases.show', $release) }}"
                               title="{{ $release->name }} · {{ $release->start_date->format('M j') }} – {{ $release->end_date->format('M j, Y') }} · {{ $release->stateLabel() }}{{ $conflict ? ' · double-booked' : '' }}"
                               @class([
                                   'absolute inset-y-1 rounded-md shadow-sm transition-opacity hover:opacity-90',
                                   'opacity-50' => $release->isComplete(),
                                   'ring-2 ring-warning-500 ring-offset-1' => $conflict,
                               ])
                               style="left: {{ $left }}%; width: {{ $width }}%; background-color: {{ $barColor }}">
                                <span class="sr-only">{{ $release->name }}, {{ $release->start_date->format('M j') }} to {{ $release->end_date->format('M j, Y') }}</span>
                            </a>
                        </div>
                    </li>
                @endforeach
            </ul>

            @if ($todayLeft !== null)
                <p class="ml-40 mt-1 flex items-center gap-1.5 text-[11px] text-slate-400 sm:ml-48">
                    <span class="h-2.5 w-px bg-danger-400"></span>Today
                </p>
            @endif
        </div>
    </div>
@endif
