{{--
    "Catch me up" on a comment thread (#6).
    @include('ai.partials.catch-up', ['url' => route('ai.catch-up.task', $task), 'count' => 7])
--}}
<section class="card" x-data="aiPanel({ url: @js($url) })" aria-label="Catch me up on the comments">
    <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-5 py-4">
        <div class="flex items-center gap-2">
            <x-icon name="chat-bubbles" variant="outline" class="h-4 w-4 text-violet-600" />
            <h3 class="text-sm font-semibold text-slate-700">Catch me up</h3>
        </div>
        <span class="text-xs text-slate-400">{{ $count }} comments</span>
    </div>
    <div class="space-y-3 px-5 py-4 text-sm">
        <template x-if="!result">
            <div class="space-y-2">
                <p class="text-slate-500">Get the decisions, open questions and next steps from this thread.</p>
                <x-ai-button label="Summarize the thread" busy-label="Reading…" click="run()" />
            </div>
        </template>
        <template x-if="result">
            <div class="space-y-3">
                <p class="leading-6 text-slate-700" x-text="result.summary"></p>
                <template x-for="section in [{ title: 'Decisions', key: 'decisions' }, { title: 'Open questions', key: 'open_questions' }]" :key="section.key">
                    <div x-show="result[section.key].length">
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500" x-text="section.title"></h4>
                        <ul class="mt-1 list-inside list-disc space-y-1 text-slate-700 marker:text-slate-400">
                            <template x-for="item in result[section.key]"><li x-text="item"></li></template>
                        </ul>
                    </div>
                </template>
                <div x-show="result.next_steps.length">
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Next steps</h4>
                    <ul class="mt-1 space-y-1 text-slate-700">
                        <template x-for="s in result.next_steps">
                            <li class="flex gap-2"><x-icon name="arrow-right" variant="outline" class="mt-0.5 h-4 w-4 flex-none text-violet-500" />
                                <span><span x-text="s.text"></span><span x-show="s.owner" class="text-slate-500" x-text="' — ' + s.owner"></span></span></li>
                        </template>
                    </ul>
                </div>
                <button type="button" @click="reset()" class="text-xs font-medium text-slate-500 hover:text-slate-700">Clear summary</button>
            </div>
        </template>
        <x-ai-error />
    </div>
</section>
