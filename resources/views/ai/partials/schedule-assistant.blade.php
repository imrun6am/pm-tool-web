{{--
    Release schedule assistant (#8), inside the release form's `releaseForm`
    scope: reads its start / end / teamId / offDays and writes the proposed
    dates into its `phases`. Nothing is saved until the form is.
    @include('ai.partials.schedule-assistant', ['release' => $release])
--}}
@if (auth()->user()->canManageReleases() && app(\App\Services\Ai\AiFeatures::class)->available('schedule_assistant'))
    <div class="mb-5 rounded-lg bg-violet-50/60 p-3 ring-1 ring-inset ring-violet-600/10"
         x-data="aiPanel({ url: @js(route('ai.schedule')) })">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs text-slate-600">
                <span class="font-medium text-slate-800">Plan with Claude</span> — phase dates sized by working days, around off-days and the team’s other releases.
            </p>
            <x-ai-button label="Suggest phases" busy-label="Planning…" disabled="!start || !end || !teamId"
                         click="run({ start_date: start, end_date: end, team_id: teamId, release_id: {{ $release->exists ? $release->id : 'null' }}, off_days: offDays })
                                .then((r) => { if (!r) return; Object.entries(r.phases).forEach(([k, p]) => { if (phases[k]) { phases[k].start = p.start; phases[k].end = p.end; } }); })" />
        </div>
        <p x-show="!start || !end || !teamId" class="mt-1 text-xs text-slate-500">Pick a team and the overall window first.</p>
        <template x-if="result">
            <div class="mt-2 space-y-1 text-xs">
                <p class="text-slate-700" x-text="result.rationale"></p>
                <template x-for="w in result.warnings"><p class="flex gap-1 text-amber-800"><span aria-hidden="true">⚠</span><span x-text="w"></span></p></template>
                <p class="text-slate-500">Phases filled in below — adjust them, then save the release.</p>
            </div>
        </template>
        <x-ai-error class="mt-2" />
    </div>
@endif
