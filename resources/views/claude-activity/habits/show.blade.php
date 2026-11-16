@php
    $facts = $report->facts ?? [];
    $ai = $report->analysis ?? [];
    $name = $report->user?->name ?? 'Deleted user';
    $period = $report->period_start->format('M j, Y').' – '.$report->period_end->format('M j, Y');
    $focus = collect($ai['projects'] ?? [])->keyBy('name');
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

                    <section class="card" aria-labelledby="habit-projects">
                        <div class="card-header"><h2 id="habit-projects" class="card-title">Projects</h2></div>
                        <ul class="divide-y divide-slate-100">
                            @foreach ($facts['projects'] ?? [] as $project)
                                <li class="px-4 py-3 sm:px-6">
                                    <p class="flex items-baseline justify-between gap-3 text-sm">
                                        <span class="font-mono font-medium text-slate-900">{{ $project['name'] }}</span>
                                        <span class="whitespace-nowrap text-xs text-slate-500">{{ $project['prompts'] }} {{ \Illuminate\Support\Str::plural('prompt', $project['prompts']) }} · {{ $project['sessions'] }} {{ \Illuminate\Support\Str::plural('session', $project['sessions']) }} · last {{ \Illuminate\Support\Carbon::parse($project['last_used'])->format('M j') }}</span>
                                    </p>
                                    @if ($focus->get($project['name'])['focus'] ?? null)
                                        <p class="mt-1 text-sm text-slate-600">{{ $focus->get($project['name'])['focus'] }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
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
