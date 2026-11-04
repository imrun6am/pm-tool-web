@php
    use App\Support\Duration;

    $hours = fn (int $minutes) => Duration::format($minutes);
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Capacity" description="Available time each week against the estimated work due that week." />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container space-y-4">
            @if (! $team)
                <x-empty-state icon="user-group" title="No teams to plan." description="You’ll see a team here once you lead one." />
            @else
                <x-filter-bar :action="route('capacity.index')">
                    <label for="team_id" class="filter-label">Team</label>
                    <select id="team_id" name="team_id" class="filter-select max-w-[14rem]">
                        @foreach ($teams as $t)
                            <option value="{{ $t->id }}" @selected($t->id === $team->id)>{{ $t->name }}</option>
                        @endforeach
                    </select>
                    @if ($from->gt(today()->startOfWeek()))
                        <a href="{{ route('capacity.index', ['team_id' => $team->id, 'from' => $from->copy()->subWeek()->toDateString()]) }}" class="btn-secondary btn-sm btn-icon" aria-label="Earlier week">‹</a>
                    @endif
                    <a href="{{ route('capacity.index', ['team_id' => $team->id, 'from' => $from->copy()->addWeek()->toDateString()]) }}" class="btn-secondary btn-sm btn-icon" aria-label="Later week">›</a>
                </x-filter-bar>

                @if (empty($capacity['people']))
                    <x-empty-state icon="user-group" title="This team has no active members." />
                @else
                    <div class="table-wrap">
                        <table class="table-base">
                            <thead>
                                <tr>
                                    <th scope="col">Person</th>
                                    @foreach ($capacity['weeks'] as $week)
                                        <th scope="col" @class(['whitespace-nowrap', 'text-brand-700' => $week['current']])>
                                            {{ $week['current'] ? 'This week' : $week['start']->format('M j') }}
                                        </th>
                                    @endforeach
                                    <th scope="col" class="whitespace-nowrap">Later or no date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($capacity['people'] as $row)
                                    <tr>
                                        <th scope="row" class="whitespace-nowrap text-left font-medium">
                                            <a href="{{ route('tasks.index', ['assignee_id' => $row['user']->id, 'sort' => 'due_date']) }}" class="flex items-center gap-2 text-sm text-slate-800 hover:text-brand-700">
                                                <x-avatar :user="$row['user']" size="sm" />{{ $row['user']->name }}
                                            </a>
                                        </th>
                                        @foreach ($row['cells'] as $cell)
                                            @php
                                                $pct = $cell['available_minutes'] > 0 ? min(100, (int) round($cell['load_minutes'] / $cell['available_minutes'] * 100)) : ($cell['load_minutes'] > 0 ? 100 : 0);
                                            @endphp
                                            <td class="min-w-[7.5rem] align-top">
                                                <p @class(['tabular whitespace-nowrap text-sm', 'font-semibold text-danger-600' => $cell['over'], 'text-slate-700' => ! $cell['over']])>
                                                    {{ $hours($cell['load_minutes']) }} <span class="font-normal text-slate-500">of {{ $hours($cell['available_minutes']) }}</span>
                                                </p>
                                                <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                                    <div @class(['h-full rounded-full', 'bg-danger-500' => $cell['over'], 'bg-brand-500' => ! $cell['over']]) style="width: {{ $pct }}%"></div>
                                                </div>
                                                @if ($cell['unestimated'] > 0)
                                                    <p class="mt-1 whitespace-nowrap text-xs text-warning-800">+{{ $cell['unestimated'] }} not estimated</p>
                                                @endif
                                            </td>
                                        @endforeach
                                        <td class="min-w-[7rem] align-top text-sm text-slate-600">
                                            @if ($row['later']['tasks'] > 0)
                                                <p class="tabular whitespace-nowrap">{{ $hours($row['later']['load_minutes']) }}</p>
                                                <p class="mt-1 whitespace-nowrap text-xs text-slate-500">{{ $row['later']['tasks'] }} {{ Str::plural('task', $row['later']['tasks']) }}</p>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="text-xs text-slate-500">Available time leaves out weekends, this team’s release off-days, recorded leave and days already past. Load is what’s left of each estimate; overdue work counts this week.</p>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
