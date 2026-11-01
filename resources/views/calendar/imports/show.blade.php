@php
    use App\Models\Event;
    use App\Models\EventImport;

    $userOptions = $users->map(fn ($u) => ['value' => $u->id, 'label' => $u->name, 'hint' => $u->email])->values();
    $pending = collect($rows)->whereNull('event_id');
    $added = collect($rows)->whereNotNull('event_id');
    $hasOld = session()->hasOldInput('events');
    $confidence = fn (float $c) => match (true) {
        $c >= 0.8 => ['High', 'badge-success'],
        $c >= 0.5 => ['Medium', 'badge-warning'],
        default => ['Low', 'badge-danger'],
    };
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$import->displayName()"
                       :description="($import->isPasted() ? 'Pasted ' : 'Uploaded ').$import->created_at->diffForHumans().' · '.$import->sizeLabel().($import->user_id !== auth()->id() ? ' · by '.($import->user?->name ?? 'deleted user') : '')"
                       :breadcrumbs="['Calendar' => route('calendar.index'), 'Import events' => route('calendar.imports.index'), $import->displayName() => null]">
            <x-slot name="actions">
                <span class="badge {{ $import->statusBadge() }}">{{ $import->statusLabel() }}</span>
                <form method="POST" action="{{ route('calendar.imports.destroy', $import) }}"
                      data-confirm="Delete this import and its {{ $import->isPasted() ? 'pasted text' : 'uploaded file' }}? Events already added stay on the calendar." data-confirm-verb="Delete">
                    @csrf @method('DELETE')
                    <button class="btn-ghost btn-sm text-rose-600 hover:bg-rose-50">
                        <x-icon name="trash" class="h-4 w-4" /> Delete
                    </button>
                </form>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-6 sm:py-8">
        {{-- Swapped in place when the job finishes (the status card below polls
             progress and asks for the refresh) — the page never reloads. --}}
        @if ($pastedText !== null)
            {{-- Outside the poll region, so it stays open while the status updates. --}}
            <div class="app-container mb-4">
                <details class="card group">
                    <summary class="flex cursor-pointer list-none items-center gap-2 px-4 py-3 text-sm font-medium text-slate-700 hover:text-slate-900">
                        <x-icon name="clipboard-check" variant="outline" class="h-4 w-4 flex-none text-slate-400" />
                        <span class="flex-none">Show pasted text</span>
                        <span class="min-w-0 truncate font-normal text-slate-400 group-open:hidden">— {{ $import->preview }}</span>
                    </summary>
                    <pre class="max-h-80 overflow-auto whitespace-pre-wrap border-t border-slate-100 px-4 py-3 font-mono text-xs leading-relaxed text-slate-700">{{ $pastedText }}</pre>
                </details>
            </div>
        @endif
        <x-poll-region id="calendar-import-{{ $import->id }}" class="app-container space-y-4"
                       :running="$import->isRunning()" every="20000">

            @if ($import->isRunning())
                {{-- Live status while the job runs --}}
                <div class="card p-6 sm:p-8"
                     x-data="{
                         label: @js($import->statusLabel()),
                         progress: {{ $import->progressPercent() }},
                         done: {{ $import->chunks_done }},
                         total: {{ $import->chunks_total }},
                         timer: null,
                         init() { this.timer = setInterval(() => this.poll(), 3000) },
                         destroy() { clearInterval(this.timer) },
                         async poll() {
                             try {
                                 const res = await fetch(@js(route('calendar.imports.status', $import)), { headers: { Accept: 'application/json' } });
                                 if (! res.ok) return;
                                 const s = await res.json();
                                 Object.assign(this, { label: s.label, progress: s.progress, done: s.chunks_done, total: s.chunks_total });
                                 if (! s.running) {
                                     clearInterval(this.timer);
                                     $dispatch('poll-region-now'); // swap in the review form, no reload
                                 }
                             } catch (e) {}
                         },
                     }">
                    <div class="flex flex-col items-center text-center">
                        <span class="relative flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 text-brand-600 ring-1 ring-inset ring-brand-600/15">
                            <x-icon name="sparkles" variant="outline" class="h-6 w-6 animate-pulse" />
                        </span>
                        <h2 class="mt-4 text-base font-semibold text-slate-900" x-text="label">{{ $import->statusLabel() }}</h2>
                        <p class="mt-1 max-w-md text-sm text-slate-500">
                            Looking for meetings, deadlines, reviews and releases. This usually takes under a minute; long documents take longer.
                            You can leave this page — the import stays in <a href="{{ route('calendar.imports.index') }}" class="font-medium text-brand-700 hover:underline">your imports</a>.
                        </p>
                        <div class="mt-5 w-full max-w-sm" x-show="total > 1" x-cloak>
                            <div class="h-1.5 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-brand-500 transition-all duration-500" :style="`width: ${progress}%`"></div>
                            </div>
                            <p class="mt-2 text-xs tabular text-slate-500">Part <span x-text="Math.min(done + 1, total)"></span> of <span x-text="total"></span></p>
                        </div>
                    </div>
                </div>

            @elseif ($import->isFailed())
                <div class="card p-6 sm:p-8">
                    <div class="flex flex-col items-center text-center">
                        <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-danger-50 text-danger-600 ring-1 ring-inset ring-danger-600/15">
                            <x-icon name="exclamation-circle" variant="outline" class="h-6 w-6" />
                        </span>
                        <h2 class="mt-4 text-base font-semibold text-slate-900">The file couldn’t be read</h2>
                        <p class="mt-1 max-w-lg text-sm text-slate-600">{{ $import->error ?? 'Unknown error.' }}</p>
                        <div class="mt-5 flex flex-wrap justify-center gap-2">
                            @can('retry', $import)
                                <form method="POST" action="{{ route('calendar.imports.retry', $import) }}">
                                    @csrf
                                    <button class="btn-primary btn-sm"><x-icon name="restore" class="h-4 w-4" /> Try again</button>
                                </form>
                            @endcan
                            <a href="{{ route('calendar.imports.index') }}" class="btn-secondary btn-sm">Upload another file</a>
                        </div>
                    </div>
                </div>

            @elseif ($rows === [])
                <div class="card">
                    <x-empty-state icon="calendar" title="No events found"
                                   description="{{ $import->isPasted() ? 'Nothing in the pasted text had a date that could be turned into an event. Check it mentions dates, or paste more of it.' : 'Nothing in this file had a date that could be turned into an event. Try a clearer copy, or another file.' }}">
                        <a href="{{ route('calendar.imports.index') }}" class="btn-primary btn-sm">Upload another file</a>
                    </x-empty-state>
                </div>

            @else
                {{-- Review --}}
                <form method="POST" action="{{ route('calendar.imports.confirm', $import) }}"
                      x-data="{
                          count: 0,
                          busy: false,
                          boxes() { return [...this.$root.querySelectorAll('input[data-include]:not(:disabled)')] },
                          recount() { this.count = this.boxes().filter(b => b.checked).length },
                          setVisibility(v) { this.$root.querySelectorAll('select[id$=-visibility]').forEach(s => s.value = v) },
                          setAll(on) { this.boxes().forEach(b => { if (b.checked !== on) { b.checked = on; b.dispatchEvent(new Event('change')) } }); this.recount() },
                      }"
                      x-init="$nextTick(() => recount())" @submit="busy = true">
                    @csrf

                    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">
                                {{ count($rows) }} {{ Str::plural('event', count($rows)) }} found
                                <span class="font-normal text-slate-500">
                                    · {{ $import->source === EventImport::SOURCE_ICS ? 'read directly from the calendar '.($import->isPasted() ? 'data' : 'file') : 'found by Claude — check dates and times' }}
                                </span>
                            </h2>
                            @if ($added->isNotEmpty())
                                <p class="mt-0.5 text-xs text-slate-500">{{ $added->count() }} already added from this import.</p>
                            @endif
                        </div>
                        @if ($canConfirm && $pending->isNotEmpty())
                            <div class="flex items-center gap-1 text-sm">
                                <button type="button" class="btn-ghost btn-sm" @click="setAll(true)">Select all</button>
                                <button type="button" class="btn-ghost btn-sm" @click="setAll(false)">Select none</button>
                                <span class="mx-1 h-4 w-px bg-slate-200" aria-hidden="true"></span>
                                <button type="button" class="btn-ghost btn-sm" @click="setVisibility('shared')" title="Make every event visible to everyone">
                                    <x-icon name="globe" variant="outline" class="h-4 w-4" />All shared
                                </button>
                                <button type="button" class="btn-ghost btn-sm" @click="setVisibility('private')" title="Make every event visible only to you and its attendees">
                                    <x-icon name="lock-closed" variant="outline" class="h-4 w-4" />All private
                                </button>
                            </div>
                        @endif
                    </div>

                    @unless ($canConfirm)
                        <div class="mb-3 flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                            <x-icon name="lock-closed" variant="outline" class="mt-0.5 h-5 w-5 flex-none text-slate-400" />
                            <p>Only {{ $import->user?->name ?? 'the uploader' }} can confirm these events.</p>
                        </div>
                    @endunless

                    <div class="space-y-3">
                        @foreach ($rows as $i => $row)
                            @php
                                $key = "events.$i";
                                [$confLabel, $confBadge] = $confidence((float) $row['confidence']);
                                $duplicate = $row['duplicate_of'] ?? null;
                                $default = ! $duplicate && $row['confidence'] >= 0.5;
                                $on = $hasOld ? old("$key.include") === '1' : $default;
                                $allDay = $hasOld ? old("$key.all_day") === '1' : (bool) $row['all_day'];
                                $releaseHintUnmatched = filled($row['release_hint'] ?? null) && empty($row['release_id']);
                            @endphp

                            @if ($row['event_id'])
                                {{-- Already on the calendar --}}
                                <div class="card flex flex-wrap items-center gap-3 px-4 py-3">
                                    <span class="flex h-6 w-6 flex-none items-center justify-center rounded-full bg-success-50 text-success-600 ring-1 ring-inset ring-success-600/20">
                                        <x-icon name="check" variant="outline" class="h-3.5 w-3.5" />
                                    </span>
                                    <span class="h-3 w-3 flex-none rounded-sm" style="background-color: {{ Event::TYPE_COLORS[$row['type']] ?? '#64748b' }}"></span>
                                    <span class="min-w-0 flex-1 truncate text-sm font-medium text-slate-800">{{ $row['title'] }}</span>
                                    <span class="text-xs tabular text-slate-500">
                                        {{ \Illuminate\Support\Carbon::parse($row['date'])->format('D j M Y') }}@unless ($row['all_day']) · {{ $row['start_time'] }}@endunless
                                    </span>
                                    @if (($row['visibility'] ?? null) === Event::VISIBILITY_PRIVATE)
                                        <span class="badge badge-slate"><x-icon name="lock-closed" variant="outline" class="h-3 w-3" />Private</span>
                                    @endif
                                    <span class="badge badge-success">Added</span>
                                    <a href="{{ route('events.show', $row['event_id']) }}" class="btn-ghost btn-sm">View event</a>
                                </div>
                                @continue
                            @endif

                            <div class="card transition-opacity" x-data="{ on: @js($on), allDay: @js($allDay) }" :class="on ? '' : 'opacity-60'">
                                {{-- Row header: include + title + type --}}
                                <div class="flex flex-wrap items-start gap-3 border-b border-slate-100 px-4 py-3">
                                    <input type="hidden" name="{{ "events[$i][include]" }}" value="0">
                                    <input type="checkbox" name="{{ "events[$i][include]" }}" value="1" data-include
                                           class="field-check mt-2.5" x-model="on" @change="recount()" @checked($on)
                                           aria-label="Include {{ $row['title'] }}" @disabled(! $canConfirm)>

                                    <div class="min-w-0 flex-1">
                                        <label for="ev-{{ $i }}-title" class="sr-only">Title</label>
                                        <input id="ev-{{ $i }}-title" name="{{ "events[$i][title]" }}" type="text" maxlength="255"
                                               value="{{ old("$key.title", $row['title']) }}"
                                               class="field-input font-medium" @invalid("$key.title")>
                                        @error("$key.title")
                                            <p id="{{ $key }}.title-error" class="field-error"><x-icon name="exclamation-circle" variant="outline" class="h-4 w-4 flex-none" />{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="w-full sm:w-36">
                                        <label for="ev-{{ $i }}-visibility" class="sr-only">Who can see it</label>
                                        <select id="ev-{{ $i }}-visibility" name="{{ "events[$i][visibility]" }}" class="field-select"
                                                title="Shared: everyone who can see the calendar. Private: only you and the attendees." @invalid("$key.visibility")>
                                            @foreach (Event::VISIBILITIES as $val => $label)
                                                <option value="{{ $val }}" @selected(old("$key.visibility", $row['visibility'] ?? Event::VISIBILITY_SHARED) === $val)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="w-full sm:w-40">
                                        <label for="ev-{{ $i }}-type" class="sr-only">Type</label>
                                        <select id="ev-{{ $i }}-type" name="{{ "events[$i][type]" }}" class="field-select" @invalid("$key.type")>
                                            @foreach (Event::TYPES as $val => $label)
                                                <option value="{{ $val }}" @selected(old("$key.type", $row['type']) === $val)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="flex w-full flex-wrap items-center gap-1.5 sm:w-auto sm:pt-2">
                                        <span class="badge {{ $confBadge }}" title="Confidence {{ (int) round($row['confidence'] * 100) }}%">{{ $confLabel }} confidence</span>
                                        @if ($duplicate)
                                            <a href="{{ route('events.show', $duplicate) }}" class="badge badge-warning hover:underline" title="An event with this title is already on the calendar that day">
                                                Possible duplicate
                                            </a>
                                        @endif
                                    </div>
                                </div>

                                {{-- Fields --}}
                                <div class="grid gap-4 px-4 py-4 sm:grid-cols-2 lg:grid-cols-12">
                                    <x-field label="Date" for="ev-{{ $i }}-date" name="{{ $key }}.date" class="lg:col-span-3">
                                        <input id="ev-{{ $i }}-date" name="{{ "events[$i][date]" }}" type="date" required
                                               value="{{ old("$key.date", $row['date']) }}" class="field-input" @invalid("$key.date")>
                                    </x-field>
                                    <x-field label="Start time" for="ev-{{ $i }}-start" name="{{ $key }}.start_time" class="lg:col-span-2">
                                        <input id="ev-{{ $i }}-start" name="{{ "events[$i][start_time]" }}" type="time"
                                               value="{{ old("$key.start_time", $row['start_time']) }}" class="field-input"
                                               :disabled="allDay" @invalid("$key.start_time")>
                                    </x-field>
                                    <x-field label="End date" for="ev-{{ $i }}-end-date" name="{{ $key }}.end_date" optional class="lg:col-span-3">
                                        <input id="ev-{{ $i }}-end-date" name="{{ "events[$i][end_date]" }}" type="date"
                                               value="{{ old("$key.end_date", $row['end_date']) }}" class="field-input" @invalid("$key.end_date")>
                                    </x-field>
                                    <x-field label="End time" for="ev-{{ $i }}-end" name="{{ $key }}.end_time" optional class="lg:col-span-2">
                                        <input id="ev-{{ $i }}-end" name="{{ "events[$i][end_time]" }}" type="time"
                                               value="{{ old("$key.end_time", $row['end_time']) }}" class="field-input"
                                               :disabled="allDay" @invalid("$key.end_time")>
                                    </x-field>
                                    <div class="flex items-end pb-2 lg:col-span-2">
                                        <input type="hidden" name="{{ "events[$i][all_day]" }}" value="0">
                                        <label class="inline-flex cursor-pointer items-center gap-2 text-sm font-medium text-slate-700">
                                            <input type="checkbox" name="{{ "events[$i][all_day]" }}" value="1" class="field-check" x-model="allDay" @checked($allDay)>
                                            All day
                                        </label>
                                    </div>

                                    <x-field label="Location" for="ev-{{ $i }}-location" name="{{ $key }}.location" optional class="lg:col-span-6">
                                        <input id="ev-{{ $i }}-location" name="{{ "events[$i][location]" }}" type="text" maxlength="255"
                                               value="{{ old("$key.location", $row['location']) }}" placeholder="Room 4 / Zoom" class="field-input" @invalid("$key.location")>
                                    </x-field>
                                    <x-field label="Related release" for="ev-{{ $i }}-release" name="{{ $key }}.release_id" optional class="lg:col-span-6"
                                             :hint="$releaseHintUnmatched ? 'The file mentions “'.$row['release_hint'].'”, which doesn’t match a release.' : null">
                                        <select id="ev-{{ $i }}-release" name="{{ "events[$i][release_id]" }}" class="field-select" @invalid("$key.release_id")>
                                            <option value="">— None —</option>
                                            @foreach ($releases as $r)
                                                <option value="{{ $r->id }}" @selected((int) old("$key.release_id", $row['release_id']) === $r->id)>{{ $r->name }} ({{ $r->year }})</option>
                                            @endforeach
                                        </select>
                                    </x-field>

                                    <x-field label="Attendees" name="{{ $key }}.attendees" optional class="sm:col-span-2 lg:col-span-12">
                                        <div class="mt-1.5">
                                            <x-multi-select :name="'events['.$i.'][attendees]'" :options="$userOptions"
                                                            :selected="old($key.'.attendees', $row['attendee_ids'])" placeholder="Add attendees…" />
                                        </div>
                                        @if ($row['unmatched_attendees'] !== [])
                                            <div class="mt-2 flex flex-wrap items-center gap-1.5 text-xs">
                                                <span class="inline-flex items-center gap-1 font-medium text-warning-800">
                                                    <x-icon name="exclamation-circle" variant="outline" class="h-3.5 w-3.5" /> Not matched to a user:
                                                </span>
                                                @foreach ($row['unmatched_attendees'] as $name)
                                                    <span class="badge badge-warning">{{ $name }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </x-field>

                                    <x-field label="Description" for="ev-{{ $i }}-description" name="{{ $key }}.description" optional class="sm:col-span-2 lg:col-span-12">
                                        <textarea id="ev-{{ $i }}-description" name="{{ "events[$i][description]" }}" rows="2" maxlength="5000"
                                                  class="field-textarea" @invalid("$key.description")>{{ old("$key.description", $row['description']) }}</textarea>
                                    </x-field>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if ($canConfirm && $pending->isNotEmpty())
                        {{-- Confirm bar --}}
                        <div class="sticky bottom-4 z-10 mt-4">
                            <div class="card flex flex-wrap items-center justify-between gap-3 px-4 py-3 shadow-pop">
                                <p class="text-sm text-slate-600">
                                    <span class="font-semibold text-slate-900 tabular" x-text="count">0</span> of {{ $pending->count() }} selected.
                                    Nothing is added until you confirm.
                                </p>
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('calendar.imports.index') }}" class="btn-ghost btn-sm">Later</a>
                                    <button type="submit" class="btn-primary" :disabled="count === 0 || busy">
                                        <x-icon name="check" variant="outline" class="h-4 w-4" />
                                        <span x-text="busy ? 'Adding…' : `Add ${count} ${count === 1 ? 'event' : 'events'} to calendar`">Add events to calendar</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif
                </form>
            @endif
        </x-poll-region>
    </div>
</x-app-layout>
