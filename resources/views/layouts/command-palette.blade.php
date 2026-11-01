@php
    // Only this user's visible menu + create actions (from App\Support\Navigation
    // via a view composer) — hidden sections never reach the browser.
    $entries = collect($navGroups)
        ->flatMap(fn ($group) => collect($group['items'])->map(fn ($item) => [
            'label' => $item['label'],
            'group' => $group['label'],
            'url' => route($item['route']),
            'icon' => $item['icon'],
            'color' => $item['color'],
        ]))
        ->concat(collect($createActions)->map(fn ($action) => [
            'label' => $action['label'],
            'group' => 'Create',
            'url' => route($action['route']),
            'icon' => $action['icon'],
            'color' => $action['color'],
        ]))
        ->values();
@endphp
{{-- ⌘K / Ctrl+K command palette: keyboard-first jump to any page or create
     action. Filtering and selection live in the `commandPalette` Alpine
     component (resources/js/app.js). --}}
<div x-data="commandPalette(@js($entries->map(fn ($e) => ['label' => $e['label'], 'group' => $e['group'], 'url' => $e['url']])), @js(route('search.tasks')))"
     @keydown.window="onKeydown($event)"
     @open-command-palette.window="show()">
    <div x-show="open" x-cloak class="fixed inset-0 z-[70] flex items-start justify-center px-4 pt-[12vh]"
         role="dialog" aria-modal="true" aria-label="Command palette"
         @keydown.escape.prevent.stop="close()">

        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-[2px]" @click="close()" aria-hidden="true"
             x-show="open" x-transition.opacity.duration.150ms></div>

        <div x-show="open"
             x-transition:enter="transition duration-150 ease-out motion-reduce:transition-none" x-transition:enter-start="opacity-0 scale-[0.98]" x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition duration-100 ease-in motion-reduce:transition-none" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-[0.98]"
             class="relative w-full max-w-xl overflow-hidden rounded-xl bg-white shadow-2xl ring-1 ring-slate-900/10">

            <div class="flex items-center gap-3 border-b border-slate-100 px-4">
                <x-icon name="magnifying-glass" variant="outline" class="h-5 w-5 flex-none text-slate-400" />
                <input x-ref="input" x-model="query" type="text" autocomplete="off" spellcheck="false"
                       role="combobox" aria-expanded="true" aria-controls="cmdk-list" aria-autocomplete="list"
                       :aria-activedescendant="total ? optionId(active) : null"
                       placeholder="Search pages, actions and tasks…" aria-label="Search pages, actions and tasks"
                       @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.enter.prevent="go()"
                       class="h-14 w-full border-0 bg-transparent px-0 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-0">
                <kbd class="kbd flex-none">Esc</kbd>
            </div>

            <ul id="cmdk-list" role="listbox" aria-label="Results" class="scroll-area max-h-80 overflow-y-auto p-2" x-show="total">
                @foreach ($entries as $i => $entry)
                    <li id="cmdk-opt-{{ $i }}" role="option" x-show="results.includes({{ $i }})"
                        :aria-selected="(results[active] === {{ $i }}).toString()"
                        @mousemove="active = results.indexOf({{ $i }})" @click="go(results.indexOf({{ $i }}))"
                        class="flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-slate-700"
                        :class="results[active] === {{ $i }} && 'bg-slate-100 text-slate-900'">
                        <x-icon :name="$entry['icon']" variant="outline" @class(['h-5 w-5 flex-none', $entry['color']]) />
                        <span class="flex-1 truncate font-medium">{{ $entry['label'] }}</span>
                        <span class="flex-none text-xs text-slate-500">{{ $entry['group'] }}</span>
                    </li>
                @endforeach
                {{-- Matching tasks (fetched as you type; only ones you may see) --}}
                <li role="presentation" x-show="tasks.length" class="px-3 pb-1 pt-3 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Tasks</li>
                <template x-for="(task, i) in tasks" :key="task.id">
                    <li :id="'cmdk-task-' + i" role="option"
                        :aria-selected="(active === results.length + i).toString()"
                        @mousemove="active = results.length + i" @click="go(results.length + i)"
                        class="flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-slate-700"
                        :class="active === results.length + i && 'bg-slate-100 text-slate-900'">
                        <x-icon name="check" variant="outline" class="h-5 w-5 flex-none text-sky-600" />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium" x-text="task.title"></span>
                            <span class="block truncate text-xs text-slate-500" x-text="task.release ? task.status_label + ' · ' + task.release : task.status_label"></span>
                        </span>
                    </li>
                </template>
            </ul>
            <p x-show="!total" class="px-4 py-10 text-center text-sm text-slate-500">No results for “<span x-text="query"></span>”</p>

            <div class="flex items-center gap-4 border-t border-slate-100 bg-slate-50/70 px-4 py-2.5 text-xs text-slate-500">
                <span class="flex items-center gap-1.5"><kbd class="kbd">↑</kbd><kbd class="kbd">↓</kbd> to navigate</span>
                <span class="flex items-center gap-1.5"><kbd class="kbd">↵</kbd> to open</span>
                <span class="flex items-center gap-1.5"><kbd class="kbd">esc</kbd> to close</span>
            </div>
        </div>
    </div>
</div>
