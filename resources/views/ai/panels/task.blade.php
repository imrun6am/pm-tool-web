{{--
    Claude on the task page: task writer (#5: description, acceptance
    criteria, subtasks, possible duplicates) and comment catch-up (#6).
    The writer fills the page's description field (#task-description); the
    task is saved only by the page's own Save.
    @include('ai.panels.task', ['task' => $task])
--}}
@php
    $ai = app(\App\Services\Ai\AiFeatures::class);
    $showWriter = $ai->available('task_writer');
    $showCatchUp = $ai->available('catch_up') && $task->comments->count() >= \App\Services\Ai\ThreadCatchUp::MIN_COMMENTS;
@endphp

@if ($showWriter)
    <section class="card" aria-labelledby="ai-writer-{{ $task->id }}"
             x-data="aiPanel({ url: @js(route('ai.tasks.draft')) })">
        <div class="flex items-center gap-2 border-b border-slate-100 px-5 py-4">
            <x-icon name="sparkles" variant="outline" class="h-4 w-4 text-violet-600" />
            <h3 id="ai-writer-{{ $task->id }}" class="text-sm font-semibold text-slate-700">Task writer</h3>
        </div>
        <div class="space-y-3 px-5 py-4 text-sm">
            <template x-if="!result">
                <div class="space-y-2">
                    <p class="text-slate-500">Claude drafts a clear description with acceptance criteria{{ $task->isSubtask() ? '' : ', suggests subtasks' }} and checks for duplicates.</p>
                    <x-ai-button label="Draft with Claude" busy-label="Writing…"
                                 click="run({ title: @js($task->title), description: document.getElementById('task-description')?.value ?? '', task_id: {{ $task->id }} })" />
                </div>
            </template>

            <template x-if="result">
                <div class="space-y-3">
                    <div x-show="result.duplicates.length" class="rounded-lg bg-warning-50 p-3 ring-1 ring-inset ring-warning-600/20">
                        <p class="text-xs font-semibold text-warning-800">Possible duplicates</p>
                        <ul class="mt-1 space-y-0.5">
                            <template x-for="d in result.duplicates" :key="d.id">
                                <li><a :href="d.url" class="text-sm text-warning-900 underline" x-text="d.title"></a>
                                    <span class="text-xs text-warning-800" x-text="'· ' + d.status"></span></li>
                            </template>
                        </ul>
                    </div>

                    <div class="rounded-lg bg-slate-50 p-3">
                        <p class="whitespace-pre-line leading-6 text-slate-700" x-text="result.text"></p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button type="button" class="btn btn-secondary btn-sm"
                                    @click="window.aiFillField('task-description', result.text); window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'info', message: 'Description filled in — save the task to keep it.' } }))">
                                Use this description
                            </button>
                        </div>
                    </div>

                    @unless ($task->isSubtask())
                        <div x-show="result.subtasks.length"
                             x-data="aiSuggestions({ createUrl: @js(route('ai.tasks.subtasks', $task)) })"
                             x-effect="items = result ? result.subtasks.map((t) => ({ title: t, selected: true })) : null">
                            <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Suggested subtasks</h4>
                            @include('ai.partials.suggestions', ['noun' => 'subtask'])
                        </div>
                    @endunless

                    <button type="button" @click="reset()" class="text-xs font-medium text-slate-500 hover:text-slate-700">Start over</button>
                </div>
            </template>
            <x-ai-error />
        </div>
    </section>
@endif

@if ($showCatchUp)
    @include('ai.partials.catch-up', ['url' => route('ai.catch-up.task', $task), 'count' => $task->comments->count()])
@endif
