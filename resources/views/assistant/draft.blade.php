{{-- One proposed change, as the record it would become. Expects `action` in
     Alpine scope; used from a template, so everything here is client-side. --}}
<article class="assistant-draft" :class="`is-${action.status}`">
    <span class="assistant-draft-line" aria-hidden="true"></span>

    {{-- An event: a calendar leaf. --}}
    <template x-if="action.preview.kind === 'event'">
        <div class="flex gap-3">
            <div class="assistant-tile" aria-hidden="true">
                <span class="assistant-tile-month" x-text="action.preview.tile.month"></span>
                <span class="assistant-tile-day" x-text="action.preview.tile.day"></span>
                <span class="assistant-tile-weekday" x-text="action.preview.tile.weekday"></span>
            </div>
            <div class="min-w-0 flex-1">
                <h4 class="assistant-draft-title" x-text="action.preview.title"></h4>
                <p class="tabular mt-0.5 text-sm font-medium text-brand-700">
                    <span class="sr-only" x-text="`${action.preview.tile.weekday} ${action.preview.tile.month} ${action.preview.tile.day}, `"></span>
                    <span x-text="action.preview.when"></span>
                </p>
            </div>
        </div>
    </template>

    {{-- A task: a board card. --}}
    <template x-if="action.preview.kind === 'task'">
        <div class="assistant-card" :data-priority="action.preview.priority || 'none'">
            <h4 class="assistant-draft-title" x-text="action.preview.title"></h4>
            <div class="mt-2 flex flex-wrap items-center gap-1.5 text-xs">
                <span class="assistant-chip" x-text="action.preview.status"></span>
                <span x-show="action.preview.priority_label" class="assistant-chip" x-text="action.preview.priority_label"></span>
                <span x-show="action.preview.due" class="assistant-chip">Due <span class="ml-1" x-text="action.preview.due"></span></span>
                <span class="ml-auto flex items-center gap-1.5 text-slate-600">
                    <template x-if="action.preview.assignee">
                        <span class="flex items-center gap-1.5">
                            <span class="assistant-avatar" aria-hidden="true" x-text="action.preview.assignee.initials"></span>
                            <span x-text="action.preview.assignee.name"></span>
                        </span>
                    </template>
                    <span x-show="!action.preview.assignee" class="text-slate-400">Unassigned</span>
                </span>
            </div>
        </div>
    </template>

    {{-- A tasksheet row: lines on a sheet. --}}
    <template x-if="action.preview.kind === 'sheet'">
        <div>
            <div class="flex items-baseline justify-between gap-2">
                <h4 class="assistant-draft-title" x-text="action.preview.title"></h4>
                <span class="flex-none text-xs text-slate-500" x-text="action.preview.day"></span>
            </div>
            <p class="text-xs text-slate-500"><span x-text="action.preview.member?.name"></span><span x-show="action.preview.team"> · <span x-text="action.preview.team"></span></span></p>
            <ul class="assistant-sheet">
                <template x-for="(line, i) in action.preview.lines" :key="i"><li x-text="line"></li></template>
            </ul>
            <p x-show="action.preview.keeps" class="mt-1.5 text-xs text-slate-500">Added after what’s already there: <span class="italic" x-text="action.preview.keeps"></span></p>
        </div>
    </template>

    {{-- Any other change: what becomes what. --}}
    <template x-if="action.preview.kind === 'change'">
        <div>
            <p x-show="action.preview.eyebrow" class="text-xs font-medium text-slate-500" x-text="action.preview.eyebrow"></p>
            <h4 class="assistant-draft-title" x-text="action.preview.title"></h4>
            <dl x-show="action.preview.rows?.length" class="mt-2 space-y-1 text-sm">
                <template x-for="row in action.preview.rows ?? []" :key="row.label">
                    <div class="flex flex-wrap items-baseline gap-x-2">
                        <dt class="w-[6.5rem] flex-none text-slate-500" x-text="row.label"></dt>
                        <dd class="min-w-0 flex-1">
                            <template x-if="row.from">
                                <span><s class="text-slate-400" x-text="row.from"></s><span class="mx-1 text-slate-400" aria-label="changes to">→</span></span>
                            </template>
                            <span class="font-medium text-slate-900" x-text="row.to ?? 'None'"></span>
                        </dd>
                    </div>
                </template>
            </dl>
        </div>
    </template>

    <p x-show="action.preview.body" class="assistant-draft-body" x-text="action.preview.body"></p>

    <dl x-show="action.preview.fields?.length" class="mt-2 space-y-0.5 text-xs">
        <template x-for="field in action.preview.fields ?? []" :key="field.label">
            <div class="flex gap-2">
                <dt class="w-[6.5rem] flex-none text-slate-500" x-text="field.label"></dt>
                <dd class="min-w-0 flex-1 text-slate-700" x-text="field.value"></dd>
            </div>
        </template>
    </dl>

    <p x-show="action.preview.warning && action.status === 'pending'" class="mt-2 flex items-start gap-1.5 text-xs font-medium text-amber-700">
        <x-icon name="exclamation-circle" variant="outline" class="h-4 w-4 flex-none" />
        <span x-text="action.preview.warning"></span>
    </p>

    {{-- Waiting on the person. --}}
    <div x-show="action.status === 'pending'" class="mt-3 flex items-center gap-2">
        <button type="button" class="btn-primary btn-sm" :disabled="action.busy || sending" @click="confirm(action)">
            <span x-text="action.busy === 'confirm' ? 'Working…' : action.preview.action"></span>
        </button>
        <button type="button" class="btn-ghost btn-sm" :disabled="action.busy || sending" @click="cancel(action)">Cancel</button>
    </div>
    <p x-show="action.localError" class="mt-2 text-xs text-rose-700" role="alert" x-text="action.localError"></p>

    {{-- How it ended. --}}
    <div x-show="action.status === 'done'" class="assistant-draft-result">
        <svg class="h-4 w-4 flex-none" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0l-3.5-3.500a1 1 0 111.4-1.4l2.8 2.790 6.8-6.790a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
        <span x-text="action.result?.label"></span>
        <a x-show="action.result?.url" :href="action.result?.url" class="ml-auto font-semibold underline-offset-2 hover:underline">Open</a>
    </div>
    <p x-show="action.status === 'cancelled'" class="mt-2 text-xs font-medium text-slate-500">Cancelled. Nothing changed.</p>
    <p x-show="action.status === 'failed' || action.status === 'expired'" class="mt-2 flex items-start gap-1.5 text-xs font-medium text-rose-700" role="alert">
        <x-icon name="exclamation-circle" variant="outline" class="h-4 w-4 flex-none" />
        <span x-text="action.error"></span>
    </p>
</article>
