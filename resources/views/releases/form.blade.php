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

    // Shared bits of markup.
    $step = 'flex h-7 w-7 flex-none items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700 ring-1 ring-inset ring-brand-600/15';
    $dashed = 'rounded-lg border border-dashed border-slate-200 bg-slate-50/60 px-4 py-3 text-xs text-slate-500';
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

    @if ($errors->any())
        <div class="mb-6 flex items-start gap-3 rounded-xl border border-danger-200 bg-danger-50 px-4 py-3 text-sm text-danger-800" role="alert">
            <x-icon name="exclamation-circle" variant="outline" class="mt-px h-5 w-5 flex-none text-danger-500" />
            <p><span class="font-medium">This release couldn't be saved yet.</span> Fix the {{ \Illuminate\Support\Str::plural('field', $errors->count()) }} marked below and try again.</p>
        </div>
    @endif

    <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] xl:grid-cols-[minmax(0,1fr)_22rem]">
    <div class="min-w-0 space-y-6">

    {{-- ============ 1. Basics ============ --}}
    <section class="card" aria-labelledby="basics-title">
        <div class="card-header justify-start">
            <span class="{{ $step }}" aria-hidden="true">1</span>
            <div class="min-w-0">
                <h2 id="basics-title" class="card-title">Basics</h2>
                <p class="card-subtitle">Name it, place it in a quarter, and say which product and team it belongs to.</p>
            </div>
        </div>
        <div class="grid gap-5 px-5 py-5 sm:grid-cols-2 sm:px-6">
            <x-field label="Release name" for="name" name="name" class="sm:col-span-2"
                     hint="Short and specific — it shows on the dashboard, board and calendar.">
                <input id="name" name="name" type="text" value="{{ old('name', $release->name) }}" required maxlength="255"
                       x-model="name" placeholder="e.g. v2.4 Checkout revamp" @invalid('name') class="field-input">
            </x-field>

            <x-field label="Project" for="project_id" name="project_id">
                <select id="project_id" name="project_id" required x-model.number="projectId" @invalid('project_id') class="field-select">
                    <option value="">— Select a project —</option>
                    @foreach ($projects as $project)
                        <option value="{{ $project->id }}" @selected((int) old('project_id', $release->project_id) === $project->id)>{{ $project->name }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field label="Owning team" for="team_id" name="team_id" hint="The team on the hook. Overlapping windows for one team are flagged, never blocked.">
                <select id="team_id" name="team_id" required x-model.number="teamId" @invalid('team_id') class="field-select">
                    <option value="">— Select a team —</option>
                    @foreach ($teams as $team)
                        <option value="{{ $team->id }}" @selected((int) old('team_id', $release->team_id) === $team->id)>{{ $team->name }}</option>
                    @endforeach
                </select>
            </x-field>

            <x-field label="Year" for="year" name="year">
                <input id="year" name="year" type="number" min="2000" max="2100" value="{{ old('year', $release->year) }}" required
                       x-model.number="year" @invalid('year') class="field-input tabular">
            </x-field>
            <x-field label="Quarter" for="quarter" name="quarter" hint="Decides which dashboard quarter it lands on.">
                <select id="quarter" name="quarter" required x-model.number="quarter" @invalid('quarter') class="field-select">
                    @foreach ([1,2,3,4] as $q)
                        <option value="{{ $q }}" @selected((int) old('quarter', $release->quarter) === $q)>Q{{ $q }}</option>
                    @endforeach
                </select>
            </x-field>

            <x-field label="Description" for="description" name="description" :optional="true" class="sm:col-span-2">
                <textarea id="description" name="description" rows="3" maxlength="5000"
                          placeholder="What's in this release, goals, notes…" @invalid('description')
                          class="field-textarea">{{ old('description', $release->description) }}</textarea>
            </x-field>
        </div>
    </section>

    {{-- ============ 2. Window & phases ============ --}}
    <section class="card" aria-labelledby="window-title">
        <div class="card-header flex-wrap">
            <div class="flex min-w-0 items-start gap-3">
                <span class="{{ $step }}" aria-hidden="true">2</span>
                <div class="min-w-0">
                    <h2 id="window-title" class="card-title">Window &amp; phases</h2>
                    <p class="card-subtitle">The full period this release occupies the team, split into Development → QA → Retest → Release.</p>
                </div>
            </div>
            <button type="button" @click="autofill()" class="btn-secondary btn-sm flex-none">
                <x-icon name="squares" variant="outline" class="h-4 w-4" />
                Auto-split evenly
            </button>
        </div>

        <div class="space-y-5 px-5 py-5 sm:px-6">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field label="Start date" for="start_date" name="start_date">
                    <input id="start_date" name="start_date" type="date" x-model="start"
                           value="{{ $startSeed }}" required @invalid('start_date') class="field-input tabular">
                </x-field>
                <x-field label="End date" for="end_date" name="end_date">
                    <input id="end_date" name="end_date" type="date" x-model="end" :min="start || null"
                           value="{{ $endSeed }}" required @invalid('end_date') class="field-input tabular">
                </x-field>
            </div>
            <p x-show="start && end && !hasWindow" x-cloak class="field-error !mt-0">
                <x-icon name="exclamation-circle" variant="outline" class="h-4 w-4 flex-none" />The end date must be on or after the start date.
            </p>

            {{-- Claude: suggest phase dates (reads start/end/teamId/offDays, writes phases) --}}
            @include('ai.partials.schedule-assistant', ['release' => $release])

            {{-- Live timeline preview — mirrors the release detail page --}}
            <div x-show="hasWindow" x-cloak>
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
                    <span class="font-medium text-slate-600">Preview</span>
                    <span class="tabular">
                        <span x-text="totalDays"></span> days
                        · <span class="font-medium text-slate-700"><span x-text="workingDays"></span> working</span>
                        <template x-if="offDaysInWindow > 0"><span> · <span x-text="offDaysInWindow"></span> off</span></template>
                    </span>
                </div>
                <div class="relative h-9 w-full overflow-hidden rounded-lg bg-slate-100 ring-1 ring-inset ring-slate-200/70">
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
            <p x-show="!hasWindow" class="{{ $dashed }}">
                Set the start and end dates to preview the phases, then use <span class="font-medium text-slate-600">Auto-split evenly</span> to seed them.
            </p>

            {{-- Phase date rows --}}
            <div class="space-y-2">
                <div class="hidden grid-cols-[10rem_1fr_1fr] gap-3 px-3 sm:grid">
                    <span class="eyebrow">Phase</span>
                    <span class="eyebrow">Start</span>
                    <span class="eyebrow">End</span>
                </div>
                @foreach (Release::PHASES as $key => $label)
                    @php $phaseError = $errors->first("phases.$key.start") ?: $errors->first("phases.$key.end"); @endphp
                    <div @class([
                            'grid gap-2 rounded-lg p-3 ring-1 ring-inset sm:grid-cols-[10rem_1fr_1fr] sm:items-center sm:gap-3',
                            'bg-slate-50/60 ring-slate-200/70' => ! $phaseError,
                            'bg-danger-50/40 ring-danger-200' => $phaseError,
                        ])>
                        <div class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 flex-none rounded-full" style="background-color: {{ $phaseColors[$key] }}"></span>
                            <span class="text-sm font-medium text-slate-700">{{ $label }}</span>
                            <span class="tabular ml-auto text-xs text-slate-400 sm:hidden"
                                  x-show="phases.{{ $key }}.start && phases.{{ $key }}.end" x-cloak
                                  x-text="(Math.round((new Date(phases.{{ $key }}.end) - new Date(phases.{{ $key }}.start)) / 86400000) + 1) + ' days'"></span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 sm:contents">
                            <div>
                                <label for="phase-{{ $key }}-start" class="sr-only">{{ $label }} start</label>
                                <input id="phase-{{ $key }}-start" type="date" name="phases[{{ $key }}][start]" x-model="phases.{{ $key }}.start"
                                       value="{{ $phaseConfig[$key]['start'] }}" :min="start || null" :max="end || null" required
                                       @invalid("phases.$key.start") class="field-input field-sm tabular !mt-0">
                            </div>
                            <div>
                                <label for="phase-{{ $key }}-end" class="sr-only">{{ $label }} end</label>
                                <input id="phase-{{ $key }}-end" type="date" name="phases[{{ $key }}][end]" x-model="phases.{{ $key }}.end"
                                       value="{{ $phaseConfig[$key]['end'] }}" :min="phases.{{ $key }}.start || start || null" :max="end || null" required
                                       @invalid("phases.$key.end") class="field-input field-sm tabular !mt-0">
                            </div>
                        </div>
                        @foreach (['start', 'end'] as $edge)
                            @error("phases.$key.$edge")
                                <p id="phases.{{ $key }}.{{ $edge }}-error" class="field-error !mt-0 sm:col-span-3">
                                    <x-icon name="exclamation-circle" variant="outline" class="h-4 w-4 flex-none" />{{ $message }}
                                </p>
                            @enderror
                        @endforeach
                    </div>
                @endforeach
                <p class="field-hint">Each phase must sit inside the window. Phases may touch or overlap — QA often starts before development wraps.</p>
            </div>
        </div>
    </section>

    {{-- ============ 3. Off-days ============ --}}
    <section class="card" aria-labelledby="offdays-title">
        <div class="card-header flex-wrap">
            <div class="flex min-w-0 items-start gap-3">
                <span class="{{ $step }}" aria-hidden="true">3</span>
                <div class="min-w-0">
                    <h2 id="offdays-title" class="card-title">Off-days <span class="field-optional">Optional</span></h2>
                    <p class="card-subtitle">Non-working days inside the window. They reduce the working-day count but never block scheduling.</p>
                </div>
            </div>
            <div class="flex flex-none gap-2">
                <button type="button" @click="markWeekends()" class="btn-secondary btn-sm">
                    <x-icon name="calendar" variant="outline" class="h-4 w-4" />Mark weekends
                </button>
                <button type="button" @click="addOffDay()" class="btn-secondary btn-sm">
                    <x-icon name="plus" variant="outline" class="h-4 w-4" />Add
                </button>
            </div>
        </div>

        <div class="px-5 py-5 sm:px-6">
            <p x-show="offDays.length === 0" class="{{ $dashed }}">
                No off-days yet. Add specific dates (holidays, off-sites) or mark every weekend in the window.
            </p>
            <div class="space-y-2" x-show="offDays.length" x-cloak>
                <template x-for="(od, i) in offDays" :key="i">
                    <div class="flex flex-wrap items-center gap-2">
                        <label :for="'off-day-' + i" class="sr-only">Off-day date</label>
                        <input :id="'off-day-' + i" type="date" :name="'off_days['+i+'][date]'" x-model="od.date" :min="start || null" :max="end || null" required
                               class="field-input field-sm tabular !mt-0 w-full sm:w-44 sm:flex-none">
                        <label :for="'off-day-reason-' + i" class="sr-only">Reason</label>
                        <input :id="'off-day-reason-' + i" type="text" :name="'off_days['+i+'][reason]'" x-model="od.reason" maxlength="255"
                               placeholder="Reason (optional)" class="field-input field-sm !mt-0 min-w-0 flex-1">
                        <button type="button" @click="removeOffDay(i)" class="btn-ghost btn-sm btn-icon flex-none text-slate-400 hover:text-danger-600" aria-label="Remove off-day">
                            <x-icon name="x-mark" variant="outline" class="h-4 w-4" />
                        </button>
                    </div>
                </template>
            </div>
            @if ($errors->has('off_days') || $errors->has('off_days.*'))
                <p id="off_days-error" class="field-error">
                    <x-icon name="exclamation-circle" variant="outline" class="h-4 w-4 flex-none" />{{ $errors->first('off_days') ?: $errors->first('off_days.*') }}
                </p>
            @endif
        </div>
    </section>

    {{-- ============ 4. Members ============ --}}
    <section class="card" aria-labelledby="members-title">
        <div class="card-header flex-wrap">
            <div class="flex min-w-0 items-start gap-3">
                <span class="{{ $step }}" aria-hidden="true">4</span>
                <div class="min-w-0">
                    <h2 id="members-title" class="card-title">Members <span class="field-optional">Optional</span></h2>
                    <p class="card-subtitle">People working on this release, drawn from the owning team.</p>
                </div>
            </div>
            <div class="flex flex-none items-center gap-2" x-show="teamId && (teamMembers[teamId] || []).length" x-cloak>
                <span class="badge badge-brand tabular" x-text="selected.length + ' selected'"></span>
                <button type="button" class="btn-ghost btn-sm"
                        @click="selected = selected.length === (teamMembers[teamId] || []).length ? [] : (teamMembers[teamId] || []).map((m) => m.id)"
                        x-text="selected.length === (teamMembers[teamId] || []).length ? 'Clear' : 'Select all'">Select all</button>
            </div>
        </div>

        <div class="px-5 py-5 sm:px-6">
            <p x-show="!teamId" class="{{ $dashed }}">
                Pick the owning team under <span class="font-medium text-slate-600">Basics</span> first to choose its members.
            </p>

            <template x-if="teamId && (teamMembers[teamId] || []).length === 0">
                <p class="{{ $dashed }}">
                    This team has no members yet. Add members from the team page first.
                </p>
            </template>

            <div x-show="teamId && (teamMembers[teamId] || []).length" x-cloak
                 class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                <template x-for="m in (teamMembers[teamId] || [])" :key="m.id">
                    <label class="flex cursor-pointer items-center gap-2.5 rounded-lg border border-slate-200 px-3 py-2 text-sm transition hover:bg-slate-50 has-[:checked]:border-brand-300 has-[:checked]:bg-brand-50">
                        <input type="checkbox" name="members[]" :value="m.id" x-model.number="selected"
                               class="field-check">
                        <span class="min-w-0">
                            <span class="block truncate font-medium text-slate-700" x-text="m.name"></span>
                            <span class="block truncate text-xs text-slate-500" x-text="m.role"></span>
                        </span>
                    </label>
                </template>
            </div>
            @if ($errors->has('members') || $errors->has('members.*'))
                <p id="members-error" class="field-error">
                    <x-icon name="exclamation-circle" variant="outline" class="h-4 w-4 flex-none" />{{ $errors->first('members') ?: $errors->first('members.*') }}
                </p>
            @endif
        </div>
    </section>

    </div>{{-- /main column --}}

    {{-- ============ Sidebar — a live summary of the form ============ --}}
    <aside class="min-w-0 lg:sticky lg:top-20">
        <div class="space-y-6">

            {{-- At a glance (live) --}}
            <section class="card" aria-labelledby="glance-title">
                <div class="card-header">
                    <h2 id="glance-title" class="card-title">At a glance</h2>
                    <span class="badge badge-brand tabular" x-text="(year || '—') + ' · ' + (quarter ? 'Q'+quarter : '—')"></span>
                </div>
                <div class="space-y-4 px-5 py-4 text-sm sm:px-6">
                    <div>
                        <p class="eyebrow">Release</p>
                        <p class="mt-0.5 break-words font-semibold" :class="name && name.trim() ? 'text-slate-900' : 'text-slate-400'"
                           x-text="name && name.trim() ? name : 'Untitled release'"></p>
                    </div>

                    <dl class="space-y-2.5">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500">Project</dt>
                            <dd class="min-w-0">
                                <template x-if="projectName">
                                    <span class="inline-flex min-w-0 items-center gap-1.5 font-medium text-slate-800">
                                        <span class="h-2.5 w-2.5 flex-none rounded-full" :style="`background-color:${projectColor}`"></span>
                                        <span class="truncate" x-text="projectName"></span>
                                    </span>
                                </template>
                                <template x-if="!projectName"><span class="text-slate-400">Not set</span></template>
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500">Team</dt>
                            <dd class="min-w-0">
                                <template x-if="teamName">
                                    <span class="inline-flex min-w-0 items-center gap-1.5 font-medium text-slate-800">
                                        <span class="h-2.5 w-2.5 flex-none rounded-full" :style="`background-color:${teamColor}`"></span>
                                        <span class="truncate" x-text="teamName"></span>
                                    </span>
                                </template>
                                <template x-if="!teamName"><span class="text-slate-400">Not set</span></template>
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500">Window</dt>
                            <dd class="tabular text-right font-medium text-slate-800">
                                <template x-if="hasWindow"><span x-text="fmtRange()"></span></template>
                                <template x-if="!hasWindow"><span class="font-normal text-slate-400">Not set</span></template>
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-3" x-show="hasWindow" x-cloak>
                            <dt class="text-slate-500">Working days</dt>
                            <dd class="tabular font-medium text-slate-800">
                                <span x-text="workingDays"></span><span class="font-normal text-slate-400"> / <span x-text="totalDays"></span></span>
                            </dd>
                        </div>
                    </dl>

                    {{-- Mini phase strip --}}
                    <div x-show="hasWindow" x-cloak>
                        <p class="eyebrow mb-1.5">Phases</p>
                        <div class="relative h-2.5 w-full overflow-hidden rounded-full bg-slate-100 ring-1 ring-inset ring-slate-200/70">
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
                                    <span class="badge badge-brand" x-text="m.name"></span>
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
            <section class="card" aria-labelledby="checklist-title">
                <div class="card-header">
                    <h2 id="checklist-title" class="card-title">Checklist</h2>
                    <span class="tabular text-xs font-medium text-slate-500"><span x-text="stepsDone"></span>/<span x-text="steps.length"></span></span>
                </div>
                <div class="px-5 pt-3 sm:px-6">
                    <div class="h-1.5 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-brand-500 transition-[width] duration-300" :style="`width:${Math.round(stepsDone / steps.length * 100)}%`"></div>
                    </div>
                </div>
                <ul class="py-2">
                    <template x-for="step in steps" :key="step.label">
                        <li class="flex items-center gap-3 px-5 py-1.5 text-sm sm:px-6">
                            <span class="flex h-5 w-5 flex-none items-center justify-center rounded-full"
                                  :class="step.done ? 'bg-success-100 text-success-600' : 'bg-slate-100 text-slate-300'">
                                <x-icon name="check" variant="outline" class="h-3 w-3" />
                            </span>
                            <span :class="step.done ? 'font-medium text-slate-700' : 'text-slate-500'" x-text="step.label"></span>
                            <span x-show="step.optional" class="ml-auto text-[11px] uppercase tracking-wide text-slate-400">optional</span>
                        </li>
                    </template>
                </ul>
            </section>

            {{-- Tips (static) --}}
            <section class="card card-pad">
                <h2 class="card-title flex items-center gap-2">
                    <x-icon name="sparkles" variant="outline" class="h-4 w-4 text-slate-400" />Tips
                </h2>
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
