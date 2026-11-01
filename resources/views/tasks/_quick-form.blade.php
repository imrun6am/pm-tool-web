@php
    /** @var \Illuminate\Support\Collection $assignees, $releases, $teams */
    $inDialog = $inDialog ?? false;
    $me = auth()->user();
    $limited = $me->hasLimitedAccess();
    // Developers/QA on one team (or none) never pick a team: it is pinned.
    $showTeam = ! $limited || $teams->count() > 1;
    $chips = ['None' => 'null', 'Today' => '0', 'Tomorrow' => '1', 'Next week' => 'nextWeek()'];
@endphp
{{-- Quick capture form: the dialog (layouts/quick-capture, fetched on open)
     and the full /tasks/new page render this same partial. --}}
<form method="POST" action="{{ route('tasks.quick.store') }}" x-data="quickTaskForm({{ $inDialog ? 'true' : 'false' }})"
      @submit="submit($event)" class="space-y-5" novalidate>
    @csrf

    <div>
        <label for="qc-title" class="sr-only">Task title</label>
        <input id="qc-title" name="title" type="text" required maxlength="255" autofocus autocomplete="off"
               value="{{ old('title') }}" placeholder="What needs doing?"
               class="block w-full border-0 border-b border-slate-200 bg-transparent px-0 pb-2 text-lg font-medium text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-0"
               :aria-invalid="errors.title ? 'true' : null" @error('title') aria-invalid="true" @enderror
               aria-describedby="qc-title-error">
        <p id="qc-title-error" class="field-error" x-show="errors.title" x-cloak>
            <x-icon name="exclamation-circle" variant="outline" class="h-4 w-4 flex-none" /><span x-text="errors.title"></span>
        </p>
        @error('title')
            <p class="field-error"><x-icon name="exclamation-circle" variant="outline" class="h-4 w-4 flex-none" />{{ $message }}</p>
        @enderror
    </div>

    <div>
        <p class="field-label" id="qc-due-label">Due</p>
        <div class="mt-1.5 flex flex-wrap items-center gap-2" role="group" aria-labelledby="qc-due-label">
            @foreach ($chips as $label => $days)
                <button type="button" @click="setDue({{ $days }})"
                        class="rounded-full border px-3 py-1 text-sm font-medium transition-colors"
                        :class="isDue({{ $days }}) ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-slate-200 text-slate-600 hover:border-slate-300 hover:bg-slate-50'"
                        :aria-pressed="isDue({{ $days }}).toString()">{{ $label }}</button>
            @endforeach
            <label for="qc-due" class="sr-only">Due date</label>
            <input id="qc-due" name="due_date" type="date" x-model="due" value="{{ old('due_date') }}"
                   class="field-control field-sm w-auto" :aria-invalid="errors.due_date ? 'true' : null">
        </div>
        <p class="field-error" x-show="errors.due_date" x-cloak x-text="errors.due_date"></p>
    </div>

    <div @class(['grid gap-4', 'sm:grid-cols-3' => $showTeam, 'sm:grid-cols-2' => ! $showTeam])>
        <div>
            <label for="qc-assignee" class="field-label">Assignee</label>
            <select id="qc-assignee" name="assignee_id" class="field-select" :aria-invalid="errors.assignee_id ? 'true' : null">
                <option value="">Unassigned</option>
                @foreach ($assignees as $person)
                    <option value="{{ $person->id }}" @selected((int) old('assignee_id', $me->id) === $person->id)>
                        {{ $person->id === $me->id ? $person->name.' (me)' : $person->name }}
                    </option>
                @endforeach
            </select>
            <p class="field-error" x-show="errors.assignee_id" x-cloak x-text="errors.assignee_id"></p>
            @error('assignee_id')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="qc-release" class="field-label">Release <span class="field-optional">Optional</span></label>
            <select id="qc-release" name="release_id" class="field-select" :aria-invalid="errors.release_id ? 'true' : null">
                <option value="">No release</option>
                @foreach ($releases as $release)
                    <option value="{{ $release->id }}" @selected((int) old('release_id') === $release->id)>{{ $release->name }}</option>
                @endforeach
            </select>
            <p class="field-error" x-show="errors.release_id" x-cloak x-text="errors.release_id"></p>
            @error('release_id')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        @if ($showTeam)
            <div>
                <label for="qc-team" class="field-label">Team @unless ($limited)<span class="field-optional">Optional</span>@endunless</label>
                <select id="qc-team" name="team_id" class="field-select" :aria-invalid="errors.team_id ? 'true' : null">
                    <option value="">{{ $limited ? 'Choose a team' : 'From release or assignee' }}</option>
                    @foreach ($teams as $team)
                        <option value="{{ $team->id }}" @selected((int) old('team_id') === $team->id)>{{ $team->name }}</option>
                    @endforeach
                </select>
                <p class="field-error" x-show="errors.team_id" x-cloak x-text="errors.team_id"></p>
                @error('team_id')<p class="field-error">{{ $message }}</p>@enderror
            </div>
        @endif
    </div>

    <div class="flex items-center justify-between gap-3 border-t border-slate-100 pt-4">
        <p class="hidden text-xs text-slate-500 sm:block">
            <kbd class="kbd">↵</kbd> to save @if ($inDialog)· <kbd class="kbd">esc</kbd> to close @endif
        </p>
        <div class="ml-auto flex items-center gap-2">
            @if ($inDialog)
                <button type="button" class="btn-secondary" @click="$dispatch('quick-capture-close')">Cancel</button>
            @endif
            <button type="submit" class="btn-primary" :disabled="busy">
                <x-icon name="plus" variant="outline" class="h-4 w-4" />
                <span x-text="busy ? 'Adding…' : 'Add task'">Add task</span>
            </button>
        </div>
    </div>
</form>
