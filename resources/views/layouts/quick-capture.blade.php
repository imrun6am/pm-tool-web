{{-- Quick capture (press C, or "New task" in ⌘K / the Create menu) and the
     keyboard shortcut sheet (press ?). The form is fetched from /tasks/new
     each time the dialog opens. Behaviour: `quickCapture` in
     resources/js/engagement.js. --}}
<div x-data="quickCapture" @keydown.window="onKeydown($event)"
     @open-quick-capture.window="show()" @quick-capture-close="close()"
     @quick-capture-saved="saved($event.detail.message)">

    <div x-show="open" x-cloak class="fixed inset-0 z-[70] flex items-start justify-center px-4 pt-[10vh]"
         role="dialog" aria-modal="true" aria-labelledby="qc-dialog-title"
         @keydown.escape.prevent.stop="close()">
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-[2px]" @click="close()" aria-hidden="true"
             x-show="open" x-transition.opacity.duration.150ms></div>

        <div x-show="open"
             x-transition:enter="transition duration-150 ease-out motion-reduce:transition-none" x-transition:enter-start="opacity-0 scale-[0.98]" x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition duration-100 ease-in motion-reduce:transition-none" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-[0.98]"
             class="relative w-full max-w-xl rounded-xl bg-white p-5 shadow-2xl ring-1 ring-slate-900/10 sm:p-6">
            <div class="mb-4 flex items-center justify-between gap-3">
                <h2 id="qc-dialog-title" class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                    <span class="flex h-6 w-6 items-center justify-center rounded-md bg-brand-50 text-brand-600">
                        <x-icon name="plus" variant="outline" class="h-4 w-4" />
                    </span>
                    New task
                </h2>
                <button type="button" @click="close()" class="btn-ghost btn-sm btn-icon" aria-label="Close">
                    <x-icon name="x-mark" variant="outline" class="h-5 w-5" />
                </button>
            </div>
            <div x-ref="body"></div>
        </div>
    </div>

    <div x-show="shortcuts" x-cloak class="fixed inset-0 z-[70] flex items-start justify-center px-4 pt-[14vh]"
         role="dialog" aria-modal="true" aria-labelledby="shortcuts-title"
         @keydown.escape.prevent.stop="closeShortcuts()">
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-[2px]" @click="closeShortcuts()" aria-hidden="true"></div>
        <div x-show="shortcuts"
             x-transition:enter="transition duration-150 ease-out motion-reduce:transition-none" x-transition:enter-start="opacity-0 scale-[0.98]" x-transition:enter-end="opacity-100 scale-100"
             class="relative w-full max-w-sm rounded-xl bg-white p-5 shadow-2xl ring-1 ring-slate-900/10">
            <div class="flex items-center justify-between">
                <h2 id="shortcuts-title" class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                    <x-icon name="command-line" variant="outline" class="h-5 w-5 text-slate-400" /> Keyboard shortcuts
                </h2>
                <button type="button" x-ref="shortcutsClose" @click="closeShortcuts()" class="btn-ghost btn-sm btn-icon" aria-label="Close">
                    <x-icon name="x-mark" variant="outline" class="h-5 w-5" />
                </button>
            </div>
            <dl class="mt-4 divide-y divide-slate-100 text-sm">
                @foreach ([
                    ['keys' => ['⌘', 'K'], 'label' => 'Search pages and tasks'],
                    ['keys' => ['C'], 'label' => 'Add a task from anywhere'],
                    ['keys' => ['?'], 'label' => 'Show these shortcuts'],
                    ['keys' => ['Esc'], 'label' => 'Close a dialog or menu'],
                ] as $shortcut)
                    <div class="flex items-center justify-between gap-4 py-2.5">
                        <dt class="text-slate-600">{{ $shortcut['label'] }}</dt>
                        <dd class="flex flex-none items-center gap-1">
                            @foreach ($shortcut['keys'] as $key)
                                <kbd class="kbd" @if ($key === '⌘') x-text="modKey" @endif>{{ $key }}</kbd>
                            @endforeach
                        </dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </div>
</div>
