{{-- The task side panel. A link opens it with data-task-panel="{{ '{{' }} route('tasks.panel', $task) }}"
     and data-task-id. Rendered at <body>, like the event drawer, to rise above the app header. --}}
<div x-data="taskPanel" @click.window="maybeOpen($event)" @keydown.escape.window="close()">
    <template x-teleport="body">
        <div class="relative z-[55]">
            <div x-show="open" x-transition.opacity style="display: none;" class="fixed inset-0 bg-slate-900/40 backdrop-blur-[2px]" @click="close()" aria-hidden="true"></div>

            <div x-show="open" style="display: none;" role="dialog" aria-modal="true" aria-label="Task details"
                 x-transition:enter="transition ease-out duration-200 motion-reduce:transition-none"
                 x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                 x-transition:leave="transition ease-in duration-150 motion-reduce:transition-none"
                 x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
                 class="fixed inset-y-0 right-0 flex w-full max-w-xl flex-col bg-slate-50 shadow-pop">
                <div class="flex flex-none items-center justify-end border-b border-slate-200 bg-white px-3 py-2">
                    <button type="button" x-ref="close" @click="close()" class="btn-ghost btn-sm btn-icon" aria-label="Close task details">
                        <x-icon name="x-mark" variant="outline" class="h-5 w-5" />
                    </button>
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto">
                    <turbo-frame id="task-panel" x-ref="frame" target="_top">
                        <p class="p-6 text-sm text-slate-500">Loading…</p>
                    </turbo-frame>
                </div>
            </div>
        </div>
    </template>
</div>
