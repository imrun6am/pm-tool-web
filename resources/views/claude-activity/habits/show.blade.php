@php
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Str;

    $facts = $report->facts ?? [];
    $ai = $report->analysis ?? [];
    $name = $report->user?->name ?? 'Deleted user';
    $period = $report->period_start->format('M j, Y').' – '.$report->period_end->format('M j, Y');
    $focus = collect($ai['projects'] ?? [])->keyBy('name');
    $projectMinutes = collect($projects)->sum('minutes');
    $duration = fn (int $minutes) => match (true) {
        $minutes < 1 => 'under a minute',
        $minutes < 60 => $minutes.' min',
        default => intdiv($minutes, 60).' h'.($minutes % 60 ? ' '.($minutes % 60).' min' : ''),
    };
    $shownProjects = 6;
    $shownDays = 7;
    $sections = [
        ['good_practices', 'Good practice', 'check', 'text-success-600'],
        ['mistakes', 'Mistakes', 'exclamation-circle', 'text-warning-600'],
        ['struggles', 'Where it went in circles', 'flag', 'text-danger-600'],
        ['improvements', 'What to improve', 'sparkles', 'text-brand-600'],
    ];
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$name.'’s Claude Code habits'" :description="$period"
                       :breadcrumbs="['Claude activity' => route('claude-activity.index'), 'Habit reports' => route('claude-activity.habits.index'), $name => null]" />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container space-y-6">
            @if (! $report->running() && $report->status !== \App\Models\ClaudeHabitReport::STATUS_COMPLETED)
                <div class="rounded-xl border border-danger-200 bg-danger-50 px-4 py-3 text-sm text-danger-800" role="alert">
                    This report couldn't be written{{ $report->error ? ': '.$report->error : '.' }}
                </div>
            @elseif ($report->running())
                <x-empty-state icon="clock" title="Still being written" description="Come back in a minute." />
            @else
                <div class="stat-strip">
                    <x-stat label="Prompts" :value="number_format($facts['prompts'] ?? 0)" icon="chat-bubbles" tone="info"
                            :sub="($facts['prompts_reviewed'] ?? 0) < ($facts['prompts'] ?? 0) ? 'latest '.number_format($facts['prompts_reviewed']).' reviewed' : null" />
                    <x-stat label="Sessions" :value="number_format($facts['sessions'] ?? 0)" icon="clock" tone="violet"
                            :sub="($facts['prompts_per_session'] ?? 0).' prompts each on average'" />
                    <x-stat label="Active days" :value="number_format($facts['active_days'] ?? 0)" icon="calendar" tone="success"
                            :sub="isset($facts['median_session_minutes']) ? 'sessions run about '.$facts['median_session_minutes'].' min' : null" />
                    <x-stat label="Busiest hours" :value="implode(', ', array_slice($facts['busiest_hours'] ?? [], 0, 2)) ?: '—'" icon="chart-bar" tone="slate"
                            :sub="implode(', ', $facts['busiest_days'] ?? [])" />
                </div>

                <section class="card card-pad">
                    <h2 class="card-title">Summary</h2>
                    <p class="mt-2 text-sm leading-relaxed text-slate-700">{{ $ai['summary'] ?? '' }}</p>
                    <p class="mt-3 text-xs text-slate-500">
                        Written by AI ({{ $report->model }}) from the start of each prompt; Claude's answers aren't recorded. Check it before acting on it.
                        Models used: {{ collect($facts['models'] ?? [])->map(fn ($n, $m) => "{$m} ({$n})")->implode(', ') ?: 'unknown' }}.
                        @if (($facts['browser_actions'] ?? 0) > 0)
                            {{ number_format($facts['browser_actions']) }} browser actions through Claude in Chrome{{ ! empty($facts['browser_sites']) ? ', mostly on '.implode(', ', array_keys($facts['browser_sites'])) : '' }}.
                        @endif
                    </p>
                </section>

                <section class="card" aria-labelledby="habit-projects" x-data="{ all: false }">
                    <div class="card-header">
                        <h2 id="habit-projects" class="card-title flex items-center gap-2">
                            <x-icon name="folder" variant="outline" class="h-5 w-5 text-slate-400" />Projects
                        </h2>
                        <span class="text-xs tabular-nums text-slate-500">
                            {{ count($projects) }} {{ Str::plural('project', count($projects)) }}@if ($projectMinutes > 0) · about {{ $duration($projectMinutes) }} in sessions @endif
                        </span>
                    </div>
                    @if (empty($projects))
                        <p class="p-4 text-sm text-slate-500 sm:p-6">No project folders were recorded in this period.</p>
                    @else
                        <ul class="divide-y divide-slate-100">
                            @foreach ($projects as $i => $project)
                                @php
                                    $about = $focus->get($project['name']);
                                    $folders = $project['folders'] ?? [];
                                    $first = Carbon::parse($project['first_used'] ?? $project['last_used']);
                                    $last = Carbon::parse($project['last_used']);
                                @endphp
                                <li class="px-4 py-4 sm:px-6" @if ($i >= $shownProjects) x-show="all" x-cloak @endif>
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <h3 class="break-all font-mono text-sm font-semibold text-slate-900">{{ $project['name'] }}</h3>
                                            @foreach ($folders as $folder)
                                                <p class="mt-0.5 break-all font-mono text-xs text-slate-500">{{ $folder }}</p>
                                            @endforeach
                                        </div>
                                        @if ($project['prompts'] === 0)
                                            <span class="badge badge-slate flex-none">Opened, no prompts</span>
                                        @endif
                                    </div>

                                    <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 rounded-lg bg-slate-50 px-3 py-2.5 text-xs sm:grid-cols-5">
                                        <div><dt class="text-slate-500">Prompts</dt><dd class="mt-0.5 font-semibold tabular-nums text-slate-900">{{ number_format($project['prompts']) }}</dd></div>
                                        <div><dt class="text-slate-500">Sessions</dt><dd class="mt-0.5 font-semibold tabular-nums text-slate-900">{{ number_format($project['sessions']) }}</dd></div>
                                        @isset($project['active_days'])
                                            <div><dt class="text-slate-500">Active days</dt><dd class="mt-0.5 font-semibold tabular-nums text-slate-900">{{ $project['active_days'] }}</dd></div>
                                        @endisset
                                        @isset($project['minutes'])
                                            <div><dt class="text-slate-500">Time in sessions</dt><dd class="mt-0.5 font-semibold tabular-nums text-slate-900">{{ $duration($project['minutes']) }}</dd></div>
                                        @endisset
                                        <div class="col-span-2 sm:col-span-1">
                                            <dt class="text-slate-500">Used</dt>
                                            <dd class="mt-0.5 font-semibold tabular-nums text-slate-900">{{ $first->isSameDay($last) ? $last->format('M j') : $first->format('M j').' – '.$last->format('M j') }}</dd>
                                        </div>
                                    </dl>

                                    @if (filled($about['focus'] ?? null))
                                        <p class="mt-3 text-sm leading-relaxed text-slate-700">{{ $about['focus'] }}</p>
                                    @endif
                                    @if (! empty($about['tasks']))
                                        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-700">
                                            @foreach ($about['tasks'] as $task)
                                                <li>{{ $task }}</li>
                                            @endforeach
                                        </ul>
                                    @endif

                                    @if (! empty($project['models']) || ! empty($project['entrypoints']))
                                        <p class="mt-3 flex flex-wrap gap-1.5">
                                            @foreach ($project['models'] ?? [] as $model)
                                                <span class="badge badge-violet font-mono">{{ $model }}</span>
                                            @endforeach
                                            @foreach ($project['entrypoints'] ?? [] as $entrypoint)
                                                <span class="badge badge-slate font-mono" title="Started from">{{ $entrypoint }}</span>
                                            @endforeach
                                        </p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        @if (count($projects) > $shownProjects)
                            <div class="flex items-center justify-between gap-3 border-t border-slate-100 px-4 py-2 sm:px-6">
                                <span class="text-xs text-slate-500" x-show="!all">+{{ count($projects) - $shownProjects }} more {{ Str::plural('project', count($projects) - $shownProjects) }}</span>
                                <span class="text-xs text-slate-500" x-show="all" x-cloak>All {{ count($projects) }} projects</span>
                                <button type="button" class="text-sm font-medium text-brand-700 hover:text-brand-800 hover:underline" @click="all = ! all" :aria-expanded="all.toString()">
                                    <span x-show="!all">Show all</span>
                                    <span x-show="all" x-cloak>Show fewer</span>
                                </button>
                            </div>
                        @endif
                    @endif
                </section>

                <section class="card" aria-labelledby="habit-days" x-data="{ all: false }">
                    <div class="card-header">
                        <h2 id="habit-days" class="card-title flex items-center gap-2">
                            <x-icon name="calendar" variant="outline" class="h-5 w-5 text-slate-400" />Day by day
                        </h2>
                        <span class="hidden text-xs text-slate-500 sm:inline">Open a day for its sessions, computers, networks and sites</span>
                    </div>
                    @if (empty($days))
                        <p class="p-4 text-sm text-slate-500 sm:p-6">No activity was recorded in this period.</p>
                    @else
                        <ul class="divide-y divide-slate-100">
                            @foreach ($days as $i => $day)
                                <li @if ($i >= $shownDays) x-show="all" x-cloak @endif>
                                    <details class="group">
                                        <summary class="flex cursor-pointer list-none flex-wrap items-start gap-x-4 gap-y-2 px-4 py-3 hover:bg-slate-50 sm:px-6 [&::-webkit-details-marker]:hidden">
                                            <div class="flex w-40 flex-none items-start gap-2">
                                                <x-icon name="chevron-right" variant="outline" class="mt-0.5 h-4 w-4 flex-none text-slate-400 transition group-open:rotate-90" />
                                                <div>
                                                    <p class="text-sm font-semibold text-slate-900">{{ Carbon::parse($day['date'])->format('D, M j') }}</p>
                                                    <p class="text-xs tabular-nums text-slate-500">{{ $day['started'] }} – {{ $day['ended'] }}</p>
                                                </div>
                                            </div>
                                            <p class="w-44 flex-none text-xs tabular-nums text-slate-600">
                                                {{ $day['prompts'] }} {{ Str::plural('prompt', $day['prompts']) }} · {{ $day['sessions'] }} {{ Str::plural('session', $day['sessions']) }}<br>
                                                <span class="text-slate-500">{{ $duration($day['minutes']) }} in sessions</span>
                                            </p>
                                            <div class="flex min-w-0 flex-1 flex-wrap gap-1.5">
                                                @forelse ($day['projects'] as $project)
                                                    <span class="badge badge-slate font-mono" title="{{ $project['prompts'] }} {{ Str::plural('prompt', $project['prompts']) }}, {{ $duration($project['minutes']) }}">
                                                        {{ $project['name'] }}<span class="ml-1 tabular-nums text-slate-500">{{ $project['prompts'] }}</span>
                                                    </span>
                                                @empty
                                                    <span class="text-xs text-slate-500">No project folder</span>
                                                @endforelse
                                            </div>
                                        </summary>

                                        <div class="space-y-4 border-t border-slate-100 bg-slate-50/60 px-4 py-4 sm:px-6">
                                            @if (! empty($day['session_list']))
                                                <div class="overflow-x-auto">
                                                    <table class="w-full min-w-[34rem] text-left text-xs">
                                                        <thead class="text-slate-500">
                                                            <tr>
                                                                <th class="py-1 pr-3 font-medium">Time</th>
                                                                <th class="py-1 pr-3 font-medium">Project</th>
                                                                <th class="py-1 pr-3 text-right font-medium">Length</th>
                                                                <th class="py-1 pr-3 text-right font-medium">Prompts</th>
                                                                <th class="py-1 pr-3 font-medium">Model</th>
                                                                <th class="py-1 font-medium">Started from</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y divide-slate-200/70 text-slate-700">
                                                            @foreach ($day['session_list'] as $session)
                                                                <tr>
                                                                    <td class="whitespace-nowrap py-1.5 pr-3 tabular-nums">{{ $session['started'] }} – {{ $session['ended'] }}</td>
                                                                    <td class="py-1.5 pr-3 font-mono">
                                                                        <a href="{{ \App\Http\Controllers\ClaudeActivityController::deviceUrl($session['device'] ?? null, ['session' => $session['id']]) }}" class="hover:text-brand-700 hover:underline" title="This session's events">{{ $session['project'] }}</a>
                                                                    </td>
                                                                    <td class="whitespace-nowrap py-1.5 pr-3 text-right tabular-nums">{{ $duration($session['minutes']) }}</td>
                                                                    <td class="py-1.5 pr-3 text-right tabular-nums">{{ $session['prompts'] }}</td>
                                                                    <td class="py-1.5 pr-3 font-mono">{{ $session['model'] ?? '—' }}</td>
                                                                    <td class="py-1.5 font-mono">{{ $session['entrypoint'] ?? '—' }}</td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            @endif
                                            <dl class="grid gap-3 text-xs sm:grid-cols-3">
                                                <div>
                                                    <dt class="font-medium text-slate-500">{{ Str::plural('Computer', count($day['computers'])) }}</dt>
                                                    <dd class="mt-0.5 break-all text-slate-800">{{ implode(', ', $day['computers']) ?: '—' }}</dd>
                                                </div>
                                                <div>
                                                    <dt class="font-medium text-slate-500">{{ Str::plural('IP address', count($day['ips'])) }}</dt>
                                                    <dd class="mt-0.5 break-all font-mono text-slate-800">{{ implode(', ', $day['ips']) ?: '—' }}</dd>
                                                </div>
                                                <div>
                                                    <dt class="font-medium text-slate-500">Sites in the browser</dt>
                                                    <dd class="mt-0.5 break-all text-slate-800">{{ collect($day['sites'])->map(fn ($n, $host) => "{$host} ({$n})")->implode(', ') ?: '—' }}</dd>
                                                </div>
                                            </dl>
                                        </div>
                                    </details>
                                </li>
                            @endforeach
                        </ul>
                        @if (count($days) > $shownDays)
                            <div class="flex items-center justify-between gap-3 border-t border-slate-100 px-4 py-2 sm:px-6">
                                <span class="text-xs text-slate-500" x-show="!all">+{{ count($days) - $shownDays }} more {{ Str::plural('day', count($days) - $shownDays) }}</span>
                                <span class="text-xs text-slate-500" x-show="all" x-cloak>All {{ count($days) }} days</span>
                                <button type="button" class="text-sm font-medium text-brand-700 hover:text-brand-800 hover:underline" @click="all = ! all" :aria-expanded="all.toString()">
                                    <span x-show="!all">Show all</span>
                                    <span x-show="all" x-cloak>Show fewer</span>
                                </button>
                            </div>
                        @endif
                    @endif
                </section>

                <div class="grid gap-6 lg:grid-cols-2">
                    <section class="card" aria-labelledby="habit-kinds">
                        <div class="card-header"><h2 id="habit-kinds" class="card-title">What they ask for</h2></div>
                        @if (empty($ai['prompt_kinds']))
                            <p class="p-4 text-sm text-slate-500 sm:p-6">No clear pattern.</p>
                        @else
                            <ul class="divide-y divide-slate-100">
                                @foreach ($ai['prompt_kinds'] as $kind)
                                    <li class="px-4 py-3 sm:px-6">
                                        <p class="flex items-baseline justify-between gap-3 text-sm">
                                            <span class="font-medium text-slate-900">{{ $kind['kind'] }}</span>
                                            <span class="whitespace-nowrap text-xs text-slate-500">{{ $kind['share'] }}</span>
                                        </p>
                                        @foreach ($kind['examples'] as $example)
                                            <p class="mt-1 text-xs italic text-slate-500">“{{ $example }}”</p>
                                        @endforeach
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>

                    @foreach ($sections as [$key, $title, $icon, $color])
                        <section class="card" aria-labelledby="habit-{{ $key }}">
                            <div class="card-header">
                                <h2 id="habit-{{ $key }}" class="card-title flex items-center gap-2">
                                    <x-icon :name="$icon" variant="outline" class="h-5 w-5 {{ $color }}" />{{ $title }}
                                </h2>
                            </div>
                            @if (empty($ai[$key]))
                                <p class="p-4 text-sm text-slate-500 sm:p-6">Nothing stood out.</p>
                            @else
                                <ul class="list-disc space-y-2 py-4 pl-9 pr-4 text-sm leading-relaxed text-slate-700 sm:pr-6">
                                    @foreach ($ai[$key] as $item)
                                        <li>{{ $item }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </section>
                    @endforeach
                </div>
            @endif

            <p class="text-xs text-slate-500">Asked for by {{ $report->requester?->name ?? 'someone' }} {{ $report->created_at->diffForHumans() }}.</p>
        </div>
    </div>
</x-app-layout>
