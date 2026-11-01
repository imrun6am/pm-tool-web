@props([
    'name',                 // submitted as name[]
    'options' => [],        // iterable of ['value' => ..., 'label' => ..., 'hint' => ?]
    'selected' => [],       // pre-selected values
    'placeholder' => 'Select…',
])
@php
    $opts = collect($options)->map(fn ($o) => [
        'value' => (string) $o['value'],
        'label' => (string) $o['label'],
        'hint' => isset($o['hint']) ? (string) $o['hint'] : null,
    ])->values();
    $sel = collect(old($name, $selected))->map(fn ($v) => (string) $v)->values();
@endphp
{{-- Friendly multi-select: chips + searchable checkbox list. Submits <name>[]. --}}
<div
    x-data="{
        open: false,
        query: '',
        options: @js($opts),
        selected: @js($sel),
        toggle(v) { this.selected = this.selected.includes(v) ? this.selected.filter(x => x !== v) : [...this.selected, v]; },
        labelFor(v) { const o = this.options.find(o => o.value === v); return o ? o.label : v; },
        filtered() { const q = this.query.trim().toLowerCase(); return q ? this.options.filter(o => o.label.toLowerCase().includes(q) || (o.hint || '').toLowerCase().includes(q)) : this.options; },
    }"
    @click.outside="open = false"
    @keydown.escape="open = false"
    class="relative"
    data-selected="{{ $sel->implode(',') }}"
>
    {{-- Submitted values --}}
    <template x-for="v in selected" :key="v">
        <input type="hidden" name="{{ $name }}[]" :value="v">
    </template>

    {{-- Control: selected chips + open toggle. min-h-[2.5rem] matches native .field-input/.field-select (40px). --}}
    <button type="button" @click="open = !open"
            :aria-expanded="open.toString()" aria-haspopup="listbox"
            class="flex min-h-[2.5rem] w-full flex-wrap items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-left text-sm text-slate-800 shadow-[0_1px_2px_0_rgb(15_23_42/0.04)] transition hover:border-slate-400 focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/15"
            :class="open && 'border-brand-500 ring-4 ring-brand-500/15'">
        <template x-if="selected.length === 0">
            <span class="px-1 text-slate-400">{{ $placeholder }}</span>
        </template>
        <template x-for="v in selected" :key="v">
            <span class="inline-flex items-center gap-1 rounded-md bg-brand-50 py-0.5 pl-2 pr-1 text-xs font-medium text-brand-700 ring-1 ring-inset ring-brand-600/15">
                <span x-text="labelFor(v)"></span>
                <button type="button" @click.stop="toggle(v)" class="flex h-4 w-4 items-center justify-center rounded text-brand-400 hover:bg-brand-100 hover:text-brand-700" :aria-label="'Remove ' + labelFor(v)">&times;</button>
            </span>
        </template>
        <svg class="ml-auto h-4 w-4 flex-none text-slate-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
    </button>

    {{-- Dropdown: search + checkbox list. z-[45] keeps it above sticky page chrome (z-40). --}}
    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-150 motion-reduce:transition-none" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-100 motion-reduce:transition-none" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="menu-panel absolute z-[45] mt-1.5 w-full overflow-hidden"
         x-effect="open && $nextTick(() => $refs.search.focus())">
        {{-- Same search box as the searchable selects (.ss-search / .ss-input). --}}
        <div class="ss-search">
            <svg class="ss-search-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.45 4.39l3.08 3.08a.75.75 0 11-1.06 1.06l-3.08-3.08A7 7 0 012 9z" clip-rule="evenodd"/></svg>
            <input type="text" x-ref="search" x-model="query" @click.stop placeholder="Search…" aria-label="Search options"
                   class="ss-input" autocomplete="off" spellcheck="false">
        </div>
        <ul class="scroll-area max-h-56 overflow-y-auto p-1.5" role="listbox" aria-multiselectable="true">
            <template x-for="opt in filtered()" :key="opt.value">
                <li>
                    <label class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm text-slate-700 transition-colors hover:bg-brand-50 hover:text-brand-700"
                           :class="selected.includes(opt.value) && 'font-medium text-brand-700'">
                        <input type="checkbox" :checked="selected.includes(opt.value)" @change="toggle(opt.value)"
                               class="field-check">
                        <span x-text="opt.label"></span>
                        <span x-show="opt.hint" class="ml-auto text-xs text-slate-500" x-text="opt.hint"></span>
                    </label>
                </li>
            </template>
            <li x-show="filtered().length === 0" class="ss-empty">No matches</li>
        </ul>
        <div x-show="selected.length" class="flex items-center justify-between border-t border-slate-100 px-3 py-1.5 text-xs">
            <span class="text-slate-400"><span x-text="selected.length"></span> selected</span>
            <button type="button" @click="selected = []" class="font-medium text-slate-500 hover:text-rose-600">Clear all</button>
        </div>
    </div>
</div>
