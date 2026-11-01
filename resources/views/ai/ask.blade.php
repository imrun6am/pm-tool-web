<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Ask your workspace"
                       description="Ask in plain English. Claude answers only from releases, tasks, notes and events you can open, and links its sources." />
    </x-slot>

    @php
        $available = app(\App\Services\Ai\AiFeatures::class)->available('workspace_ask');
        $examples = auth()->user()->hasLimitedAccess()
            ? ['What’s on my plate this week?', 'Which of my tasks are overdue?', 'What did we decide in the last standup?']
            : ['Which releases are at risk this month?', 'What is blocking the current release?', 'What did the team ship last month?'];
    @endphp

    <div class="py-6 sm:py-8">
        <div class="app-container">
            @unless ($available)
                <x-empty-state icon="sparkles" title="Ask your workspace is not available"
                               description="An administrator has switched it off, or Claude isn’t configured yet." class="card" />
            @else
                <div class="mx-auto max-w-3xl" x-data="aiAsk({ url: @js(route('ai.ask')) })">
                    {{-- Conversation --}}
                    <div class="space-y-5" aria-live="polite">
                        <template x-if="turns.length === 0">
                            <div class="card card-pad text-center">
                                <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl bg-violet-50 text-violet-600 ring-1 ring-inset ring-violet-600/10">
                                    <x-icon name="sparkles" variant="outline" class="h-6 w-6" />
                                </span>
                                <h2 class="mt-3 text-sm font-semibold text-slate-900">What would you like to know?</h2>
                                <div class="mt-4 flex flex-wrap justify-center gap-2">
                                    @foreach ($examples as $example)
                                        <button type="button" @click="ask(@js($example))"
                                                class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-violet-50 hover:text-violet-700">
                                            {{ $example }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </template>

                        <template x-for="(turn, i) in turns" :key="i">
                            <div class="space-y-3">
                                <div class="flex justify-end">
                                    <p class="max-w-[85%] rounded-2xl rounded-br-md bg-brand-600 px-4 py-2.5 text-sm text-white" x-text="turn.question"></p>
                                </div>
                                <div class="card card-pad">
                                    <template x-if="!turn.answer">
                                        <p class="flex items-center gap-2 text-sm text-violet-700" role="status">
                                            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".3" stroke-width="3" />
                                                <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                                            </svg>
                                            Looking through your workspace…
                                        </p>
                                    </template>
                                    <template x-if="turn.answer">
                                        <div class="space-y-4">
                                            <div class="space-y-2 text-sm leading-6 text-slate-700 [&_.ai-ref]:mx-0.5 [&_.ai-ref]:rounded [&_.ai-ref]:bg-violet-50 [&_.ai-ref]:px-1 [&_.ai-ref]:py-px [&_.ai-ref]:font-mono [&_.ai-ref]:text-[11px] [&_.ai-ref]:font-medium [&_.ai-ref]:text-violet-700 hover:[&_.ai-ref]:bg-violet-100 [&_ul]:list-disc [&_ul]:space-y-1 [&_ul]:pl-5" x-html="turn.answer.html"></div>
                                            <div x-show="turn.answer.sources.length" class="border-t border-slate-100 pt-3">
                                                <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sources</h3>
                                                <ul class="mt-1.5 flex flex-wrap gap-1.5">
                                                    <template x-for="s in turn.answer.sources" :key="s.ref">
                                                        <li><a :href="s.url" class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-1 text-xs text-slate-700 hover:bg-violet-50 hover:text-violet-700">
                                                            <span class="font-mono text-[10px] text-slate-500" x-text="s.ref"></span>
                                                            <span class="max-w-[16rem] truncate" x-text="s.title"></span></a></li>
                                                    </template>
                                                </ul>
                                            </div>
                                            <div x-show="i === turns.length - 1 && turn.answer.follow_ups.length" class="flex flex-wrap gap-2">
                                                <template x-for="f in turn.answer.follow_ups">
                                                    <button type="button" @click="ask(f)" class="rounded-full border border-violet-200 px-3 py-1 text-xs text-violet-700 hover:bg-violet-50" x-text="f"></button>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                        <div x-ref="end"></div>
                    </div>

                    {{-- Composer --}}
                    <form @submit.prevent="ask()" class="sticky bottom-4 mt-6">
                        <div class="card flex items-end gap-2 p-2 shadow-lg">
                            <label for="ai-question" class="sr-only">Your question</label>
                            <textarea id="ai-question" x-model="question" rows="1" maxlength="500"
                                      @keydown.enter.prevent="if (!$event.shiftKey) ask()"
                                      placeholder="Ask about releases, tasks, meetings, notes…"
                                      class="block max-h-40 min-h-[2.5rem] flex-1 resize-none border-0 bg-transparent px-2 py-2 text-sm focus:ring-0"></textarea>
                            <button type="submit" :disabled="busy || question.trim().length < 3" class="btn btn-primary btn-sm">
                                <span x-text="busy ? 'Thinking…' : 'Ask'">Ask</span>
                            </button>
                        </div>
                        <x-ai-error class="mt-2" />
                        <p class="mt-2 text-center text-xs text-slate-400">Claude can make mistakes — check the linked sources.</p>
                    </form>
                </div>
            @endunless
        </div>
    </div>
</x-app-layout>
