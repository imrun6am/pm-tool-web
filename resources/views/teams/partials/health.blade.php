{{--
    Team health for leads (teams.show): today's tasksheet fill and standup
    attendance over the last working days. Expects $health from
    TeamService::tasksheetFill() / standupAttendance(), and $tasksheetUrl.
--}}
@php
    $sheet = $health['tasksheet'];
    $standup = $health['standup'];
    $fillPct = $sheet['roster'] > 0 ? (int) round($sheet['logged'] / $sheet['roster'] * 100) : 0;
    $missingShown = $sheet['missing']->take(6);
    $rate = $standup['rate'];
    $rateClass = match (true) {
        $rate === null => 'text-slate-400',
        $rate >= 80 => 'text-success-700',
        $rate >= 50 => 'text-warning-700',
        default => 'text-danger-700',
    };
@endphp
<section class="card overflow-hidden" aria-labelledby="team-health-title">
    <div class="card-header">
        <div class="min-w-0">
            <h2 id="team-health-title" class="card-title">Team health</h2>
            <p class="card-subtitle">Visible to leads only.</p>
        </div>
        <a href="{{ $tasksheetUrl }}" class="flex-none text-sm font-medium text-brand-700 hover:text-brand-800">Tasksheet</a>
    </div>

    {{-- Today's tasksheet --}}
    <div class="px-5 py-4 sm:px-6">
        <div class="flex items-baseline justify-between gap-3">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Tasksheet today</h3>
            @if ($sheet['roster'] > 0)
                <span class="tabular text-xs text-slate-500">{{ $fillPct }}%</span>
            @endif
        </div>

        @if ($sheet['roster'] === 0)
            <p class="mt-2 text-sm text-slate-500">No developers or QA on today’s sheet.</p>
        @else
            <p class="mt-1.5 text-sm text-slate-600">
                <span class="tabular text-2xl font-semibold tracking-tight text-slate-900">{{ $sheet['logged'] }}</span>
                <span class="text-slate-400">/ {{ $sheet['roster'] }}</span>
                logged
                @if ($sheet['onLeave'])
                    <span class="text-slate-400">· {{ $sheet['onLeave'] }} on leave</span>
                @endif
            </p>
            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100"
                 role="img" aria-label="{{ $sheet['logged'] }} of {{ $sheet['roster'] }} members logged today">
                <div @class(['h-full rounded-full', 'bg-success-500' => $fillPct === 100, 'bg-brand-500' => $fillPct < 100])
                     style="width: {{ $fillPct }}%"></div>
            </div>

            @if (today()->isWeekend())
                <p class="mt-2 text-xs text-slate-400">It’s the weekend — no sheet is expected today.</p>
            @elseif ($sheet['missing']->isNotEmpty())
                <div class="mt-3">
                    <p class="text-xs text-slate-500">Nothing logged yet:</p>
                    <ul class="mt-1.5 flex flex-wrap gap-1.5">
                        @foreach ($missingShown as $person)
                            <li class="inline-flex max-w-full items-center gap-1.5 rounded-full bg-slate-100 py-0.5 pl-0.5 pr-2 text-xs text-slate-700">
                                <x-avatar :user="$person" size="xs" />
                                <span class="truncate">{{ $person->name }}</span>
                            </li>
                        @endforeach
                        @if ($sheet['missing']->count() > $missingShown->count())
                            <li class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">
                                +{{ $sheet['missing']->count() - $missingShown->count() }} more
                            </li>
                        @endif
                    </ul>
                </div>
            @else
                <p class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-success-700">
                    <x-icon name="check" variant="outline" class="h-3.5 w-3.5" />Everyone has logged today.
                </p>
            @endif
        @endif
    </div>

    {{-- Standup attendance --}}
    <div class="border-t border-slate-100 px-5 py-4 sm:px-6">
        <div class="flex items-baseline justify-between gap-3">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Standup · last {{ count($standup['days']) }} working days</h3>
        </div>
        <p class="mt-1.5 text-sm text-slate-600">
            <span class="tabular text-2xl font-semibold tracking-tight {{ $rateClass }}">{{ $rate === null ? '—' : $rate.'%' }}</span>
            @if ($standup['marked'] > 0)
                <span class="text-slate-500">{{ $standup['attended'] }} of {{ $standup['marked'] }} marked attended</span>
            @else
                <span class="text-slate-400">No attendance marked yet</span>
            @endif
        </p>

        <ol class="mt-3 grid grid-cols-7 gap-1.5" aria-label="Standup attendance by day">
            @foreach ($standup['days'] as $day)
                @php
                    $pct = $day['marked'] > 0 ? (int) round($day['attended'] / $day['marked'] * 100) : null;
                    $summary = $day['date']->format('D, M j').': '.($pct === null ? 'not marked' : "{$day['attended']} of {$day['marked']} attended");
                @endphp
                <li class="flex flex-col items-center gap-1" title="{{ $summary }}">
                    <span class="relative flex h-12 w-full items-end overflow-hidden rounded-md bg-slate-100">
                        @if ($pct !== null)
                            <span @class([
                                'block w-full rounded-md',
                                'bg-success-500' => $pct >= 80,
                                'bg-warning-500' => $pct >= 50 && $pct < 80,
                                'bg-danger-400' => $pct < 50,
                            ]) style="height: {{ max($pct, 6) }}%"></span>
                        @else
                            <span class="absolute inset-x-1.5 bottom-1.5 border-t border-dashed border-slate-300" aria-hidden="true"></span>
                        @endif
                    </span>
                    <span @class(['text-[10px] font-medium', 'text-brand-700' => $day['today'], 'text-slate-400' => ! $day['today']])>
                        {{ $day['today'] ? 'Today' : $day['date']->format('D') }}
                    </span>
                    <span class="sr-only">{{ $summary }}</span>
                </li>
            @endforeach
        </ol>
    </div>
</section>
