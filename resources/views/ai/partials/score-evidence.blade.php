{{--
    "Show evidence" (#10) inside a member's card on the Evaluate page: a
    factual summary of their tasksheet and board record for the period being
    rated. Lead-only (the page itself is), on request, and never a score.
    @include('ai.partials.score-evidence', ['team' => $team, 'member' => $rowUser, 'period' => $period])
--}}
@if (app(\App\Services\Ai\AiFeatures::class)->available('score_evidence'))
    <div class="mx-4 mt-3 rounded-lg bg-white p-3 ring-1 ring-inset ring-violet-200"
         x-data="aiPanel({ url: @js(route('ai.performance.evidence')) })">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="flex items-center gap-1.5 text-xs font-medium text-slate-700">
                <x-icon name="sparkles" variant="outline" class="h-4 w-4 text-violet-600" />
                Evidence from {{ $member->name }}’s record — it never suggests a score.
            </p>
            <x-ai-button x-show="!result" label="Show evidence" busy-label="Reading the record…"
                         click="run({ team_id: {{ $team->id }}, user_id: {{ $member->id }}, cadence: @js($period['type']), date: @js($period['start']->toDateString()) })" />
        </div>
        <template x-if="result">
            <div class="mt-3 space-y-3 text-sm">
                <p class="leading-6 text-slate-700" x-text="result.summary"></p>
                <div class="grid gap-3 md:grid-cols-2">
                    <template x-for="section in [{ title: 'Strengths', key: 'strengths', color: 'text-emerald-700' }, { title: 'Worth a look', key: 'concerns', color: 'text-amber-700' }]" :key="section.key">
                        <div x-show="result[section.key].length">
                            <h4 class="text-xs font-semibold uppercase tracking-wide" :class="section.color" x-text="section.title"></h4>
                            <ul class="mt-1 space-y-1.5">
                                <template x-for="item in result[section.key]">
                                    <li class="text-slate-700"><span x-text="item.text"></span>
                                        <span x-show="item.evidence" class="block text-xs text-slate-500" x-text="item.evidence"></span></li>
                                </template>
                            </ul>
                        </div>
                    </template>
                </div>
                <p x-show="result.data_gaps.length" class="text-xs text-slate-500" x-text="'Gaps in the record: ' + result.data_gaps.join(' · ')"></p>
                <button type="button" @click="reset()" class="text-xs font-medium text-slate-500 hover:text-slate-700">Hide</button>
            </div>
        </template>
        <x-ai-error class="mt-2" />
    </div>
@endif
