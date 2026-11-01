@php
    $fmt = fn ($dt) => $dt ? \Illuminate\Support\Carbon::parse($dt)->format('Y-m-d\TH:i') : '';
@endphp
{{-- Event fields, stacked for the narrow drawer (events/_drawer). --}}
<div class="space-y-7">
    <section class="space-y-5">
        <h3 class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Details</h3>

        <x-field label="Title" for="title">
            <input id="title" name="title" type="text" value="{{ old('title', $event->title) }}" required
                   placeholder="e.g. Sprint planning" class="field-input" @invalid('title')>
        </x-field>

        <div class="grid gap-5 sm:grid-cols-2">
            <x-field label="Type" for="type">
                <select id="type" name="type" class="field-select" @invalid('type')>
                    @foreach (\App\Models\Event::TYPES as $val => $label)
                        <option value="{{ $val }}" @selected(old('type', $event->type) === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field label="Related release" for="release_id" optional>
                <select id="release_id" name="release_id" class="field-select" @invalid('release_id')>
                    <option value="">— None —</option>
                    @foreach ($releases as $r)
                        <option value="{{ $r->id }}" @selected((int) old('release_id', $event->release_id) === $r->id)>{{ $r->name }} ({{ $r->year }})</option>
                    @endforeach
                </select>
            </x-field>
        </div>

        @php $visibility = old('visibility', $event->visibility ?? \App\Models\Event::VISIBILITY_SHARED); @endphp
        <fieldset>
            <legend class="field-label">Who can see it</legend>
            <div class="mt-1.5 grid gap-2 sm:grid-cols-2">
                @foreach ([
                    'shared' => ['globe', 'Shared', 'Everyone who can see the calendar.'],
                    'private' => ['lock-closed', 'Private', 'Only you and the attendees you invite.'],
                ] as $value => [$icon, $label, $hint])
                    <label class="flex cursor-pointer items-start gap-2.5 rounded-lg border border-slate-200 p-3 transition-colors hover:border-slate-300 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50/50 has-[:checked]:ring-1 has-[:checked]:ring-brand-500/30">
                        <input type="radio" name="visibility" value="{{ $value }}" class="mt-0.5 h-4 w-4 border-slate-300 text-brand-600 focus:ring-brand-500/30" @checked($visibility === $value)>
                        <span class="min-w-0">
                            <span class="flex items-center gap-1.5 text-sm font-medium text-slate-800">
                                <x-icon :name="$icon" variant="outline" class="h-4 w-4 text-slate-500" />{{ $label }}
                            </span>
                            <span class="mt-0.5 block text-xs text-slate-500">{{ $hint }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            @error('visibility')
                <p id="visibility-error" class="field-error"><x-icon name="exclamation-circle" variant="outline" class="h-4 w-4 flex-none" />{{ $message }}</p>
            @enderror
        </fieldset>

        <x-field label="Description" for="description" optional>
            <textarea id="description" name="description" rows="3" class="field-textarea"
                      placeholder="Agenda, links or anything attendees should prepare." @invalid('description')>{{ old('description', $event->description) }}</textarea>
        </x-field>
    </section>

    <section class="space-y-5 border-t border-slate-100 pt-6">
        <div>
            <h3 class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">When and where</h3>
            <p class="mt-1 text-xs text-slate-500">For all-day events the time is ignored — the event covers the whole day(s).</p>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <x-field label="Starts" for="starts_at">
                <input id="starts_at" name="starts_at" type="datetime-local" value="{{ old('starts_at', $fmt($event->starts_at)) }}" required
                       class="field-input" @invalid('starts_at')>
            </x-field>
            <x-field label="Ends" for="ends_at" optional>
                <input id="ends_at" name="ends_at" type="datetime-local" value="{{ old('ends_at', $fmt($event->ends_at)) }}"
                       class="field-input" @invalid('ends_at')>
            </x-field>
        </div>

        <label class="inline-flex cursor-pointer items-center gap-2 text-sm font-medium text-slate-700">
            <input type="checkbox" name="all_day" value="1" @checked(old('all_day', $event->all_day)) class="field-check">
            All day
        </label>

        <x-field label="Location" for="location" optional>
            <input id="location" name="location" type="text" value="{{ old('location', $event->location) }}"
                   placeholder="Room 4 / Zoom" class="field-input" @invalid('location')>
        </x-field>
    </section>

    <section class="space-y-5 border-t border-slate-100 pt-6">
        <div>
            <h3 class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Attendees</h3>
            <p class="mt-1 text-xs text-slate-500">Who should be there. They’ll see the event on their calendar.</p>
        </div>

        <x-field label="Attendees" name="attendees" optional>
            <div class="mt-1.5">
                <x-multi-select
                    name="attendees"
                    :options="$users->map(fn ($u) => ['value' => $u->id, 'label' => $u->name, 'hint' => $u->roleLabel()])"
                    :selected="$selectedAttendees ?? []"
                    placeholder="Add attendees…" />
            </div>
        </x-field>
    </section>
</div>
