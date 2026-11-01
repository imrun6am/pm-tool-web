{{--
    Review list for Claude-proposed tasks, inside an `aiSuggestions` component.
    Each row can be unticked or edited before anything is created.
    Optional: $people (list of {id, name}) adds an assignee picker; $dates adds due dates;
    $noun ("task" / "subtask") words the button.
--}}
@php
    $noun = $noun ?? 'task';
    $withPeople = $withPeople ?? false;
    $withDates = $withDates ?? false;
@endphp
<div x-show="items !== null" x-cloak class="mt-3 space-y-3">
    <template x-if="items && items.length === 0">
        <p class="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600">Claude found nothing new to add.</p>
    </template>

    <template x-if="items && items.length > 0">
        <div class="space-y-2">
            <ul class="divide-y divide-slate-100 rounded-lg border border-slate-200 bg-white">
                <template x-for="(item, i) in items" :key="i">
                    <li class="flex items-start gap-3 px-3 py-2.5" :class="item.selected ? '' : 'opacity-60'">
                        <input type="checkbox" x-model="item.selected" class="mt-1.5 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                               :aria-label="'Include ' + item.title">
                        <div class="min-w-0 flex-1 space-y-1.5">
                            <input type="text" x-model="item.title" maxlength="255" class="field-input field-sm w-full font-medium"
                                   :aria-label="'Title of suggestion ' + (i + 1)">
                            <p x-show="item.description" x-text="item.description" class="text-xs leading-5 text-slate-500"></p>
                            @if ($withPeople || $withDates)
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($withPeople)
                                        <select x-model.number="item.assignee_id" class="field-select field-sm w-auto" :aria-label="'Assignee of ' + item.title">
                                            <option :value="null">Unassigned</option>
                                            <template x-for="p in (meta.people ?? [])" :key="p.id">
                                                <option :value="p.id" x-text="p.name" :selected="p.id === item.assignee_id"></option>
                                            </template>
                                        </select>
                                        <span x-show="item.owner && !item.assignee_id" class="text-xs text-amber-700"
                                              x-text="'“' + item.owner + '” not matched'"></span>
                                    @endif
                                    @if ($withDates)
                                        <input type="date" x-model="item.due_date" class="field-input field-sm w-auto" :aria-label="'Due date of ' + item.title">
                                    @endif
                                </div>
                            @endif
                        </div>
                    </li>
                </template>
            </ul>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" @click="create()" :disabled="creating || chosen.length === 0" class="btn btn-primary btn-sm">
                    <span x-text="creating ? 'Adding…' : 'Add ' + chosen.length + ' ' + (chosen.length === 1 ? @js($noun) : @js(Str::plural($noun)))"></span>
                </button>
                <button type="button" @click="discard()" class="btn btn-ghost btn-sm">Discard</button>
                <span class="text-xs text-slate-500">Nothing is added until you confirm.</span>
            </div>
        </div>
    </template>
</div>
