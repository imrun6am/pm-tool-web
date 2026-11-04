{{--
    One dialog per page for assignee suggestions (see aiAssignee in resources/js/ai.js).
    Nothing is assigned until the viewer picks someone.
--}}
<div x-data="aiAssignee" @ai-suggest-assignee.window="show($event.detail)">
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[55] flex items-center justify-center p-4" role="dialog" aria-modal="true"
             aria-labelledby="ai-assignee-title" @keydown.escape.window="open && close()">
            <div class="absolute inset-0 bg-slate-900/40" @click="close()" aria-hidden="true"></div>
            <div class="relative w-full max-w-md rounded-xl bg-white p-5 shadow-xl">
                <div class="flex items-center gap-2">
                    <x-icon name="sparkles" variant="outline" class="h-4 w-4 text-violet-600" />
                    <h2 id="ai-assignee-title" class="text-sm font-semibold text-slate-800">Suggested assignees</h2>
                </div>
                <p class="mt-1 line-clamp-2 text-xs text-slate-500" x-text="title"></p>

                <p x-show="busy" class="mt-4 text-sm text-slate-500" role="status">Claude is looking at recent work and current load…</p>

                <ul x-show="suggestions" class="mt-4 space-y-2">
                    <template x-for="person in suggestions ?? []" :key="person.id">
                        <li class="rounded-lg border border-slate-200 p-3">
                            <div class="flex items-center justify-between gap-3">
                                <span class="min-w-0 truncate text-sm font-medium text-slate-800" x-text="person.name"></span>
                                <button type="button" class="btn btn-secondary btn-sm flex-none" @click="assign(person)" :disabled="assigning !== null"
                                        x-text="assigning === person.id ? 'Assigning…' : 'Assign'"></button>
                            </div>
                            <p class="mt-1 text-xs leading-5 text-slate-600" x-text="person.reason"></p>
                            <p class="mt-1 text-xs text-slate-500">
                                <span x-text="person.open_tasks + (person.open_tasks === 1 ? ' open task' : ' open tasks')"></span><span x-show="person.overdue_tasks"> · <span x-text="person.overdue_tasks"></span> overdue</span><span x-show="person.on_leave" class="text-warning-700"> · on leave soon</span>
                            </p>
                        </li>
                    </template>
                </ul>

                <x-ai-error class="mt-3" />

                <div class="mt-4 flex justify-end">
                    <button type="button" class="btn btn-ghost btn-sm" @click="close()">Close</button>
                </div>
            </div>
        </div>
    </template>
</div>
