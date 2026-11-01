{{--
    Claude's standup digest (#4) for the team and day the tasksheet shows.
    Leads only (org-level leads: any team; a team lead: teams they lead).
    Generated automatically each weekday at 08:30 for the previous weekday.
    @include('ai.panels.standup-digest', ['team' => $team, 'day' => $day])
--}}
@php
    $viewer = auth()->user();
    $show = $team && ! $day->isFuture()
        && $viewer->canAccessTeamPerformance($team)
        && app(\App\Services\Ai\AiFeatures::class)->available('standup_digest');
@endphp
@if ($show)
    <section class="card" x-data="{ open: {{ $day->isSameDay(today()->subWeekday()) ? 'true' : 'false' }} }"
             aria-labelledby="ai-digest-{{ $team->id }}">
        <button type="button" @click="open = !open" :aria-expanded="open"
                class="flex w-full items-center justify-between gap-3 px-5 py-4 text-left">
            <span class="flex items-center gap-2">
                <x-icon name="sparkles" variant="outline" class="h-4 w-4 text-violet-600" />
                <span id="ai-digest-{{ $team->id }}" class="text-sm font-semibold text-slate-800">Standup digest</span>
                <span class="text-xs text-slate-500">{{ $team->name }} · {{ $day->format('D, M j') }}</span>
            </span>
            <x-icon name="chevron-right" variant="outline" class="h-4 w-4 text-slate-400 transition" x-bind:class="open && 'rotate-90'" />
        </button>
        <div x-show="open" x-cloak class="border-t border-slate-100 px-5 py-4">
            @include('ai.partials.insight', [
                'kind' => \App\Models\AiInsight::KIND_STANDUP_DIGEST,
                'subject' => $team,
                'forDate' => $day->copy()->startOfDay(),
                'body' => ['subject_id' => $team->id, 'date' => $day->toDateString()],
                'canRequest' => true,
                'label' => 'Summarize this day',
                'refresh' => 'Summarize again',
                'empty' => $day->isToday()
                    ? 'Today’s digest is best generated once the team has filled the tasksheet.'
                    : 'Claude summarizes the day’s tasksheet and board movement: highlights, blockers and follow-ups.',
            ])
        </div>
    </section>
@endif
