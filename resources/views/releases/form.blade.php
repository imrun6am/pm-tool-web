@php
    use App\Models\Release;
    $phaseColors = Release::PHASE_COLORS;

    // Reactive seed for the Alpine form (respects old() input on validation errors).
    $phaseConfig = [];
    foreach (Release::PHASES as $key => $label) {
        $pv = $phaseValues[$key] ?? null;
        $phaseConfig[$key] = [
            'start' => old("phases.$key.start", optional($pv?->start_date)->toDateString()) ?? '',
            'end' => old("phases.$key.end", optional($pv?->end_date)->toDateString()) ?? '',
        ];
    }
    $startSeed = old('start_date', optional($release->start_date)->toDateString()) ?? '';
    $endSeed = old('end_date', optional($release->end_date)->toDateString()) ?? '';

    // Lookup maps so the live summary can name the chosen project/team.
    $projectMap = $projects->mapWithKeys(fn ($p) => [$p->id => ['name' => $p->name, 'color' => $p->color]])->all();
    $teamMap = $teams->mapWithKeys(fn ($t) => [$t->id => ['name' => $t->name, 'color' => $t->color]])->all();
@endphp

<div x-data="releaseForm({
        start: @js($startSeed),
        end: @js($endSeed),
        phaseKeys: @js(array_keys(Release::PHASES)),
        phaseLabels: @js(Release::PHASES),
        phaseColors: @js($phaseColors),
        phases: @js($phaseConfig),
        offDays: @js($offDayValues ?? []),
        name: @js(old('name', $release->name) ?? ''),
        year: @js((int) old('year', $release->year) ?: null),
        quarter: @js((int) old('quarter', $release->quarter) ?: null),
        projectId: @js((int) old('project_id', $release->project_id)) || null,
        projectMap: @js($projectMap),
        teamId: @js((int) old('team_id', $release->team_id)) || null,
        teamMap: @js($teamMap),
        teamMembers: @js($teamMembers ?? (object) []),
        selected: @js(array_map('intval', (array) old('members', $memberValues ?? []))),
     })">

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">

    {{-- ============ Release details ============ --}}
    <section class="card overflow-hidden">
        <div class="flex items-start gap-3 border-b border-slate-100 px-5 py-4 sm:px-6">
            <span class="flex h-9 w-9 flex-none items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M2.695 14.762l-1.262 3.155a.5.5 0 00.65.65l3.155-1.262a4 4 0 001.343-.885L17.5 5.501a2.121 2.121 0 00-3-3L3.58 13.419a4 4 0 00-.885 1.343z"/></svg>
            </span>
            <div>
                <h3 class="text-sm font-semibold text-slate-800">Release details</h3>
                <p class="text-xs text-slate-500">Name it, and place it in a quarter so it lands on the right dashboard.</p>
            </div>
        </div>
        <div class="space-y-5 p-5 sm:p-6">
            <div>
                <label for="name" class="field-label">Release name</label>
                <input id="name" name="name" type="text" value="{{ old('name', $release->name) }}" required
                       x-model="name" placeholder="e.g. v2.4 Checkout revamp" class="field-input">
                @error('name') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="year" class="field-label">Year</label>
                    <input id="year" name="year" type="number" min="2000" max="2100" value="{{ old('year', $release->year) }}" required
                           x-model.number="year" class="field-input">
                    @error('year') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="quarter" class="field-label">Quarter</label>
                    <select id="quarter" name="quarter" required x-model.number="quarter" class="field-input">
                        @foreach ([1,2,3,4] as $q)
                            <option value="{{ $q }}" @selected((int) old('quarter', $release->quarter) === $q)>Q{{ $q }}</option>
                        @endforeach
                    </select>
                    @error('quarter') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="description" class="field-label">Description <span class="text-slate-400">(optional)</span></label>
                <textarea id="description" name="description" rows="3"
                          placeholder="What's in this release, goals, notes…"
                          class="field-textarea">{{ old('description', $release->description) }}</textarea>
                @error('description') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </div>
    </section>

    {{-- ============ Ownership ============ --}}
    <section class="card overflow-hidden">
        <div class="flex items-start gap-3 border-b border-slate-100 px-5 py-4 sm:px-6">
            <span class="flex h-9 w-9 flex-none items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M3.75 3A1.75 1.75 0 002 4.75v3.26a3.235 3.235 0 011.75-.51h12.5c.644 0 1.245.188 1.75.51V6.75A1.75 1.75 0 0016.25 5h-4.836a.25.25 0 01-.177-.073L9.823 3.513A1.75 1.75 0 008.586 3H3.75z"/><path d="M3.75 9A1.75 1.75 0 002 10.75v4.5c0 .966.784 1.75 1.75 1.75h12.5A1.75 1.75 0 0018 15.25v-4.5A1.75 1.75 0 0016.25 9H3.75z"/></svg>
            </span>
            <div>
                <h3 class="text-sm font-semibold text-slate-800">Ownership</h3>
                <p class="text-xs text-slate-500">Which product this ships for, and the team on the hook for it.</p>
            </div>
        </div>
        <div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-6">
            <div>
                <label for="project_id" class="field-label">Project</label>
                <select id="project_id" name="project_id" required x-model.number="projectId" class="field-input">
                    <option value="">— Select a project —</option>
                    @foreach ($projects as $project)
                        <option value="{{ $project->id }}" @selected((int) old('project_id', $release->project_id) === $project->id)>{{ $project->name }}</option>
                    @endforeach
                </select>
                @error('project_id') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="team_id" class="field-label">Owning team</label>
                <select id="team_id" name="team_id" required x-model.number="teamId" class="field-input">
                    <option value="">— Select a team —</option>
                    @foreach ($teams as $team)
                        <option value="{{ $team->id }}" @selected((int) old('team_id', $release->team_id) === $team->id)>{{ $team->name }}</option>
                    @endforeach
                </select>
                @error('team_id') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </div>
    </section>

    {{-- ============ Members ============ --}}
    <section class="card overflow-hidden">
        <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4 sm:px-6">
            <div class="flex items-start gap-3">
                <span class="flex h-9 w-9 flex-none items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 8a3 3 0 100-6 3 3 0 000 6zM3.465 14.493a1.23 1.23 0 00.41 1.412A9.957 9.957 0 0010 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 00-13.074.003z"/></svg>
                </span>
                <div>
                    <h3 class="text-sm font-semibold text-slate-800">Members</h3>
                    <p class="text-xs text-slate-500">People working on this release, drawn from the owning team.</p>
                </div>
            </div>
            <span class="badge bg-brand-50 text-brand-700" x-show="teamId && (teamMembers[teamId] || []).length" x-cloak
                  x-text="selected.length + ' selected'"></span>
        </div>

        <div class="p-5 sm:p-6">
            <p x-show="!teamId"
               class="rounded-md border border-dashed border-slate-200 bg-slate-50/60 px-4 py-3 text-xs text-slate-400">
                Select an owning team first to pick its members.
            </p>

            <template x-if="teamId && (teamMembers[teamId] || []).length === 0">
                <p class="rounded-md border border-dashed border-slate-200 bg-slate-50/60 px-4 py-3 text-xs text-slate-400">
                    This team has no members yet. Add members from the team page first.
                </p>
            </template>

            <div x-show="teamId && (teamMembers[teamId] || []).length" x-cloak
                 class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                <template x-for="m in (teamMembers[teamId] || [])" :key="m.id">
                    <label class="flex items-center gap-2.5 rounded-lg border border-slate-200 px-3 py-2 text-sm transition hover:bg-slate-50 has-[:checked]:border-brand-300 has-[:checked]:bg-brand-50">
                        <input type="checkbox" name="members[]" :value="m.id" x-model.number="selected"
                               class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        <span class="min-w-0">
                            <span class="block truncate font-medium text-slate-700" x-text="m.name"></span>
                            <span class="block truncate text-xs text-slate-400" x-text="m.role"></span>
                        </span>
                    </label>
                </template>
            </div>
            @error('members') <p class="field-error">{{ $message }}</p> @enderror
            @error('members.*') <p class="field-error">{{ $message }}</p> @enderror
        </div>
    </section>

    {{-- ============ Overall window ============ --}}
    <section class="card overflow-hidden">
        <div class="flex items-start gap-3 border-b border-slate-100 px-5 py-4 sm:px-6">
            <span class="flex h-9 w-9 flex-none items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.75 2a.75.75 0 01.75.75V4h7V2.75a.75.75 0 011.5 0V4h.25A2.75 2.75 0 0118 6.75v8.5A2.75 2.75 0 0115.25 18H4.75A2.75 2.75 0 012 15.25v-8.5A2.75 2.75 0 014.75 4H5V2.75A.75.75 0 015.75 2zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75z" clip-rule="evenodd"/></svg>
            </span>
            <div>
                <h3 class="text-sm font-semibold text-slate-800">Overall window</h3>
                <p class="text-xs text-slate-500">The full period this release occupies the team. Phases must sit inside it.</p>
            </div>
        </div>
        <div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-6">
            <div>
                <label for="start_date" class="field-label">Start date</label>
                <input id="start_date" name="start_date" type="date" x-model="start"
                       value="{{ $startSeed }}" required class="field-input">
                @error('start_date') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="end_date" class="field-label">End date</label>
                <input id="end_date" name="end_date" type="date" x-model="end"
                       value="{{ $endSeed }}" required class="field-input">
                @error('end_date') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </div>
    </section>

    {{-- ============ Phases ============ --}}
    <section class="card overflow-hidden">
        <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4 sm:px-6">
            <div class="flex items-start gap-3">
                <span class="flex h-9 w-9 flex-none items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M4.25 2A2.25 2.25 0 002 4.25v2.5A2.25 2.25 0 004.25 9h2.5A2.25 2.25 0 009 6.75v-2.5A2.25 2.25 0 006.75 2h-2.5zm0 9A2.25 2.25 0 002 13.25v2.5A2.25 2.25 0 004.25 18h2.5A2.25 2.25 0 009 15.75v-2.5A2.25 2.25 0 006.75 11h-2.5zm9-9A2.25 2.25 0 0011 4.25v2.5A2.25 2.25 0 0013.25 9h2.5A2.25 2.25 0 0018 6.75v-2.5A2.25 2.25 0 0015.75 2h-2.5zm0 9A2.25 2.25 0 0011 13.25v2.5A2.25 2.25 0 0013.25 18h2.5A2.25 2.25 0 0018 15.75v-2.5A2.25 2.25 0 0015.75 11h-2.5z"/></svg>
                </span>
                <div>
                    <h3 class="text-sm font-semibold text-slate-800">Phases</h3>
                    <p class="text-xs text-slate-500">Development → QA → Retest → Release, each with its own dates.</p>
                </div>
            </div>
            <button type="button" @click="autofill()" class="btn-secondary btn-sm flex-none">
                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/></svg>
                Auto-split evenly
            </button>
        </div>

        <div class="p-5 sm:p-6">
            {{-- Live timeline preview — mirrors the release detail page --}}
            <div x-show="hasWindow" x-cloak>
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
                    <span class="font-medium text-slate-600">Timeline preview</span>
                    <span>
                        <span x-text="totalDays"></span> days
                        · <span class="font-medium text-slate-700"><span x-text="workingDays"></span> working</span>
                        <template x-if="offDaysInWindow > 0"><span> · <span x-text="offDaysInWindow"></span> off</span></template>
                    </span>
                </div>
                <div class="relative h-9 w-full overflow-hidden rounded-md bg-slate-100 ring-1 ring-inset ring-slate-200">
                    <template x-for="seg in segments()" :key="seg.key">
                        <div class="absolute top-0 flex h-9 items-center justify-center overflow-hidden text-[11px] font-medium text-white"
                             :style="`left:${seg.left}%;width:${seg.width}%;background-color:${seg.color}`"
                             :title="seg.label">
                            <span class="truncate px-1" x-text="seg.label"></span>
                        </div>
                    </template>
                    <template x-for="(tick, i) in offTicks()" :key="'off'+i">
                        <div class="absolute top-0 z-10 h-9 bg-slate-900/25"
                             :style="`left:${tick.left}%;width:${tick.width}%`"
                             :title="tick.reason ? (tick.date + ' — ' + tick.reason) : tick.date"></div>
                    </template>
                </div>
            </div>
            <div x-show="!hasWindow" x-cloak
                 class="rounded-md border border-dashed border-slate-200 bg-slate-50/60 px-4 py-3 text-xs text-slate-400">
                Set the overall window above to preview the phase timeline.
            </div>

            {{-- Phase date rows --}}
            <div class="mt-5 space-y-3">
                <div class="hidden grid-cols-[160px_1fr_1fr] gap-3 sm:grid">
                    <span class="eyebrow">Phase</span>
                    <span class="eyebrow">Start</span>
                    <span class="eyebrow">End</span>
                </div>
                @foreach (Release::PHASES as $key => $label)
                    <div class="grid items-center gap-3 rounded-lg border border-slate-100 bg-slate-50/40 p-3 sm:grid-cols-[160px_1fr_1fr] sm:border-0 sm:bg-transparent sm:p-0">
                        <div class="flex items-center gap-2">
                            <span class="h-3 w-3 rounded-full" style="background-color: {{ $phaseColors[$key] }}"></span>
                            <span class="text-sm font-medium text-slate-700">{{ $label }}</span>
                        </div>
                        <div>
                            <input type="date" name="phases[{{ $key }}][start]" x-model="phases.{{ $key }}.start"
                                   value="{{ $phaseConfig[$key]['start'] }}" :min="start || null" :max="end || null" required class="field-input !mt-0">
                            @error("phases.$key.start") <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <input type="date" name="phases[{{ $key }}][end]" x-model="phases.{{ $key }}.end"
                                   value="{{ $phaseConfig[$key]['end'] }}" :min="start || null" :max="end || null" required class="field-input !mt-0">
                            @error("phases.$key.end") <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============ Off-days ============ --}}
    <section class="card overflow-hidden">
        <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4 sm:px-6">
            <div class="flex items-start gap-3">
                <span class="flex h-9 w-9 flex-none items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM6.75 9.25a.75.75 0 000 1.5h6.5a.75.75 0 000-1.5h-6.5z" clip-rule="evenodd"/></svg>
                </span>
                <div>
                    <h3 class="text-sm font-semibold text-slate-800">Off-days <span class="font-normal text-slate-400">(optional)</span></h3>
                    <p class="text-xs text-slate-500">Non-working days inside the window. They reduce the working-day count.</p>
                </div>
            </div>
            <div class="flex flex-none gap-2">
                <button type="button" @click="markWeekends()" class="btn-secondary btn-sm">Mark weekends</button>
                <button type="button" @click="addOffDay()" class="btn-secondary btn-sm">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z"/></svg>
                    Add
                </button>
            </div>
        </div>

        <div class="p-5 sm:p-6">
            <p x-show="offDays.length === 0"
               class="rounded-md border border-dashed border-slate-200 bg-slate-50/60 px-4 py-3 text-xs text-slate-400">
                No off-days yet. Add specific dates or mark all weekends in the window.
            </p>
            <div class="space-y-2">
                <template x-for="(od, i) in offDays" :key="i">
                    <div class="grid items-center gap-3 sm:grid-cols-[210px_1fr_auto]">
                        <input type="date" :name="'off_days['+i+'][date]'" x-model="od.date" :min="start || null" :max="end || null" required class="field-input !mt-0">
                        <input type="text" :name="'off_days['+i+'][reason]'" x-model="od.reason" placeholder="Reason (optional)" class="field-input !mt-0">
                        <button type="button" @click="removeOffDay(i)" class="justify-self-start rounded-lg p-2 text-slate-400 transition hover:bg-rose-50 hover:text-rose-600 sm:justify-self-auto" aria-label="Remove off-day">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </section>

    </div>{{-- /main column --}}

    {{-- ============ Right sidebar — mirrors the form, top to bottom ============ --}}
    <aside class="lg:col-span-1">
        <div class="space-y-6 lg:sticky lg:top-6">

            {{-- At a glance (live) --}}
            <section class="card overflow-hidden">
                <div class="card-header">
                    <h3 class="text-sm font-semibold text-slate-800">At a glance</h3>
                    <span class="badge bg-brand-50 text-brand-700" x-text="(year || '—') + ' · ' + (quarter ? 'Q'+quarter : '—')"></span>
                </div>
                <div class="space-y-4 p-5 text-sm sm:p-6">
                    <div>
                        <p class="eyebrow">Release</p>
                        <p class="mt-0.5 font-semibold" :class="name ? 'text-slate-800' : 'text-slate-400'"
                           x-text="name && name.trim() ? name : 'Untitled release'"></p>
                    </div>

                    <dl class="space-y-3">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500">Project</dt>
                            <dd>
                                <template x-if="projectName">
                                    <span class="inline-flex items-center gap-1.5 font-medium text-slate-800">
                                        <span class="h-2.5 w-2.5 rounded-full" :style="`background-color:${projectColor}`"></span>
                                        <span x-text="projectName"></span>
                                    </span>
                                </template>
                                <template x-if="!projectName"><span class="text-slate-400">Not set</span></template>
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500">Team</dt>
                            <dd>
                                <template x-if="teamName">
                                    <span class="inline-flex items-center gap-1.5 font-medium text-slate-800">
                                        <span class="h-2.5 w-2.5 rounded-full" :style="`background-color:${teamColor}`"></span>
                                        <span x-text="teamName"></span>
                                    </span>
                                </template>
                                <template x-if="!teamName"><span class="text-slate-400">Not set</span></template>
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500">Window</dt>
                            <dd class="text-right font-medium text-slate-800">
                                <template x-if="hasWindow"><span x-text="fmtRange()"></span></template>
                                <template x-if="!hasWindow"><span class="font-normal text-slate-400">Not set</span></template>
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-3" x-show="hasWindow" x-cloak>
                            <dt class="text-slate-500">Working days</dt>
                            <dd class="font-medium text-slate-800">
                                <span x-text="workingDays"></span><span class="font-normal text-slate-400"> / <span x-text="totalDays"></span></span>
                            </dd>
                        </div>
                    </dl>

                    {{-- Mini phase timeline --}}
                    <div x-show="hasWindow" x-cloak>
                        <p class="eyebrow mb-1.5">Timeline</p>
                        <div class="relative h-2.5 w-full overflow-hidden rounded bg-slate-100 ring-1 ring-inset ring-slate-200">
                            <template x-for="seg in segments()" :key="seg.key">
                                <div class="absolute top-0 h-2.5" :style="`left:${seg.left}%;width:${seg.width}%;background-color:${seg.color}`" :title="seg.label"></div>
                            </template>
                        </div>
                    </div>

                    {{-- Members --}}
                    <div>
                        <p class="eyebrow mb-1.5">Members (<span x-text="selectedMembers.length"></span>)</p>
                        <template x-if="selectedMembers.length">
                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="m in selectedMembers" :key="m.id">
                                    <span class="inline-flex items-center rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700" x-text="m.name"></span>
                                </template>
                            </div>
                        </template>
                        <template x-if="!selectedMembers.length">
                            <p class="text-xs text-slate-400">No members selected yet.</p>
                        </template>
                    </div>
                </div>
            </section>

            {{-- Checklist (live, follows the form order) --}}
            <section class="card overflow-hidden">
                <div class="card-header">
                    <h3 class="text-sm font-semibold text-slate-800">Checklist</h3>
                    <span class="text-xs font-medium text-slate-400"><span x-text="stepsDone"></span>/<span x-text="steps.length"></span></span>
                </div>
                <ul class="divide-y divide-slate-100">
                    <template x-for="step in steps" :key="step.label">
                        <li class="flex items-center gap-3 px-5 py-2.5 text-sm sm:px-6">
                            <span class="flex h-5 w-5 flex-none items-center justify-center rounded-full"
                                  :class="step.done ? 'bg-emerald-100 text-emerald-600' : 'bg-slate-100 text-slate-300'">
                                <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0l-3.5-3.5a1 1 0 111.4-1.4l2.8 2.79 6.8-6.79a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
                            </span>
                            <span :class="step.done ? 'font-medium text-slate-700' : 'text-slate-500'" x-text="step.label"></span>
                            <span x-show="step.optional" class="ml-auto text-[11px] uppercase tracking-wide text-slate-300">optional</span>
                        </li>
                    </template>
                </ul>
            </section>

            {{-- Tips (static) --}}
            <section class="card card-pad">
                <h3 class="text-sm font-semibold text-slate-800">Tips</h3>
                <ul class="mt-2 list-disc space-y-1.5 pl-4 text-xs text-slate-500">
                    <li>Phases must sit inside the overall window — use <span class="font-medium text-slate-600">Auto-split evenly</span> to seed them.</li>
                    <li>Off-days reduce the working-day count but don't block scheduling.</li>
                    <li>Members are drawn from the owning team — add people on the team page first.</li>
                </ul>
            </section>

        </div>
    </aside>
    </div>{{-- /grid --}}
</div>

<script>
    function releaseForm(config) {
        const fmt = (d) => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        const parse = (s) => new Date(s + 'T00:00:00');
        const diff = (a, b) => Math.round((parse(b) - parse(a)) / 86400000);
        const clamp = (v) => Math.max(0, Math.min(100, v));
        const toast = (type, message) => window.dispatchEvent(new CustomEvent('toast', { detail: { type, message } }));

        return {
            start: config.start || '',
            end: config.end || '',
            phaseKeys: config.phaseKeys,
            phaseLabels: config.phaseLabels,
            phaseColors: config.phaseColors,
            phases: config.phases,
            offDays: config.offDays || [],
            teamId: config.teamId || null,
            teamMembers: config.teamMembers || {},
            selected: config.selected || [],
            name: config.name || '',
            year: config.year || null,
            quarter: config.quarter || null,
            projectId: config.projectId || null,
            projectMap: config.projectMap || {},
            teamMap: config.teamMap || {},

            get projectName() { return (this.projectMap[this.projectId] || {}).name || null; },
            get projectColor() { return (this.projectMap[this.projectId] || {}).color || '#cbd5e1'; },
            get teamName() { return (this.teamMap[this.teamId] || {}).name || null; },
            get teamColor() { return (this.teamMap[this.teamId] || {}).color || '#cbd5e1'; },
            get selectedMembers() {
                return (this.teamMembers[this.teamId] || []).filter((m) => this.selected.includes(m.id));
            },
            get phasesComplete() {
                return this.phaseKeys.every((k) => this.phases[k] && this.phases[k].start && this.phases[k].end);
            },
            get steps() {
                return [
                    { label: 'Name & quarter', done: !!(this.name && this.name.trim() && this.year && this.quarter) },
                    { label: 'Project', done: !!this.projectId },
                    { label: 'Owning team', done: !!this.teamId },
                    { label: 'Members', done: this.selected.length > 0, optional: true },
                    { label: 'Overall window', done: this.hasWindow },
                    { label: 'Phase dates', done: this.phasesComplete },
                ];
            },
            get stepsDone() { return this.steps.filter((s) => s.done).length; },
            fmtRange() {
                if (!this.hasWindow) return '';
                const s = parse(this.start).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
                const e = parse(this.end).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
                return s + ' – ' + e;
            },

            get hasWindow() {
                return !!(this.start && this.end && diff(this.start, this.end) >= 0);
            },
            get totalDays() {
                return this.hasWindow ? diff(this.start, this.end) + 1 : 0;
            },
            get offDaysInWindow() {
                if (!this.hasWindow) return 0;
                return this.offDays.filter((o) => o.date && o.date >= this.start && o.date <= this.end).length;
            },
            get workingDays() {
                return Math.max(this.totalDays - this.offDaysInWindow, 0);
            },
            segments() {
                if (!this.hasWindow) return [];
                const total = this.totalDays;
                const out = [];
                for (const key of this.phaseKeys) {
                    const p = this.phases[key];
                    if (!p || !p.start || !p.end || diff(p.start, p.end) < 0) continue;
                    const left = diff(this.start, p.start) / total * 100;
                    const width = (diff(p.start, p.end) + 1) / total * 100;
                    if (isNaN(left) || isNaN(width)) continue;
                    out.push({ key, label: this.phaseLabels[key], color: this.phaseColors[key], left: clamp(left), width: clamp(width) });
                }
                return out;
            },
            offTicks() {
                if (!this.hasWindow) return [];
                const total = this.totalDays;
                return this.offDays
                    .filter((o) => o.date && o.date >= this.start && o.date <= this.end)
                    .map((o) => ({ left: clamp(diff(this.start, o.date) / total * 100), width: 1 / total * 100, date: o.date, reason: o.reason }));
            },

            addOffDay() { this.offDays.push({ date: '', reason: '' }); },
            removeOffDay(i) { this.offDays.splice(i, 1); },
            markWeekends() {
                if (!this.start || !this.end) { toast('warning', 'Set the overall start and end dates first.'); return; }
                const start = parse(this.start);
                const end = parse(this.end);
                const existing = new Set(this.offDays.map((o) => o.date));
                let added = 0;
                for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
                    const iso = fmt(d);
                    if ((d.getDay() === 0 || d.getDay() === 6) && !existing.has(iso)) {
                        this.offDays.push({ date: iso, reason: 'Weekend' });
                        existing.add(iso);
                        added++;
                    }
                }
                toast(added ? 'success' : 'info', added ? (added + ' weekend day(s) added.') : 'All weekends are already marked.');
            },
            autofill() {
                if (!this.start || !this.end) { toast('warning', 'Set the overall start and end dates first.'); return; }
                const start = parse(this.start);
                const end = parse(this.end);
                const totalDays = Math.floor((end - start) / 86400000) + 1;
                if (totalDays < this.phaseKeys.length) { toast('warning', 'The window is too short to split into four phases.'); return; }
                const per = Math.floor(totalDays / this.phaseKeys.length);
                let cursor = new Date(start);
                this.phaseKeys.forEach((key, i) => {
                    const isLast = i === this.phaseKeys.length - 1;
                    const pStart = new Date(cursor);
                    let pEnd = new Date(cursor);
                    pEnd.setDate(pEnd.getDate() + (isLast ? (totalDays - per * i) : per) - 1);
                    if (isLast) pEnd = new Date(end);
                    this.phases[key].start = fmt(pStart);
                    this.phases[key].end = fmt(pEnd);
                    cursor = new Date(pEnd);
                    cursor.setDate(cursor.getDate() + 1);
                });
                toast('success', 'Phases split evenly across the window.');
            },
        };
    }
</script>
