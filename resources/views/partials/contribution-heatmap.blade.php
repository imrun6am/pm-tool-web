{{--
    GitHub-style daily activity grid: one column per week (Monday on top),
    shaded on a single-hue sequential ramp by `level` 0–4.
    @include('partials.contribution-heatmap', ['weeks' => $analytics['heatmap']['weeks']])
    Each day: date (Carbon), count, level, tasks, comments, logged, future.
--}}
@php
    $weeks = $weeks ?? [];
    $ramp = ['bg-slate-100', 'bg-emerald-200', 'bg-emerald-400', 'bg-emerald-600', 'bg-emerald-800'];
    $describe = function (array $day): string {
        if ($day['count'] === 0) {
            return $day['date']->format('D, M j').': no activity';
        }
        $parts = [];
        if ($day['tasks']) $parts[] = $day['tasks'].' '.\Illuminate\Support\Str::plural('task', $day['tasks']).' done';
        if ($day['comments']) $parts[] = $day['comments'].' '.\Illuminate\Support\Str::plural('comment', $day['comments']);
        if ($day['logged']) $parts[] = 'tasksheet logged';
        return $day['date']->format('D, M j').': '.implode(', ', $parts);
    };
    $total = collect($weeks)->flatten(1)->sum('count');
    $activeDays = collect($weeks)->flatten(1)->where('count', '>', 0)->count();
@endphp
<div class="overflow-x-auto pb-1">
    <div class="inline-flex min-w-full flex-col gap-1" role="img"
         aria-label="Daily activity over the last {{ count($weeks) }} weeks: {{ $total }} contributions on {{ $activeDays }} days">
        {{-- month labels, placed over the first week that starts in that month --}}
        <div class="flex gap-[3px] pl-7 text-[10px] text-slate-400" aria-hidden="true">
            @foreach ($weeks as $i => $week)
                @php $first = $week[0]['date']; @endphp
                <span class="w-3 flex-none overflow-visible whitespace-nowrap">{{ ($i === 0 || $first->day <= 7) ? $first->format('M') : '' }}</span>
            @endforeach
        </div>
        <div class="flex gap-[3px]" aria-hidden="true">
            <div class="flex w-6 flex-none flex-col gap-[3px] text-[10px] leading-3 text-slate-400">
                @foreach (['Mon', '', 'Wed', '', 'Fri', '', ''] as $dayLabel)
                    <span class="h-3">{{ $dayLabel }}</span>
                @endforeach
            </div>
            @foreach ($weeks as $week)
                <div class="flex flex-none flex-col gap-[3px]">
                    @foreach ($week as $day)
                        @if ($day['future'])
                            <span class="h-3 w-3 rounded-[3px]"></span>
                        @else
                            <span class="h-3 w-3 rounded-[3px] ring-1 ring-inset ring-slate-900/5 {{ $ramp[$day['level']] }}"
                                  title="{{ $describe($day) }}"></span>
                        @endif
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>
</div>
<div class="mt-3 flex items-center justify-end gap-1.5 text-[11px] text-slate-500" aria-hidden="true">
    Less
    @foreach ($ramp as $shade)
        <span class="h-3 w-3 rounded-[3px] ring-1 ring-inset ring-slate-900/5 {{ $shade }}"></span>
    @endforeach
    More
</div>
