@php
    $fmt = fn ($dt) => $dt ? \Illuminate\Support\Carbon::parse($dt)->format('Y-m-d\TH:i') : '';
@endphp
<div class="card card-pad">
    <x-form-section title="Details" description="What the event is, and the release it belongs to, if any.">
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

        <x-field label="Description" for="description" optional>
            <textarea id="description" name="description" rows="3" class="field-textarea"
                      placeholder="Agenda, links or anything attendees should prepare." @invalid('description')>{{ old('description', $event->description) }}</textarea>
        </x-field>
    </x-form-section>

    <x-form-section title="When and where" description="For all-day events the time is ignored — the event covers the whole day(s).">
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
                   placeholder="Room 4 / Zoom" class="field-input sm:max-w-md" @invalid('location')>
        </x-field>
    </x-form-section>

    <x-form-section title="Attendees" description="Who should be there. They’ll see the event on their calendar.">
        <x-field label="Attendees" name="attendees" optional>
            <div class="mt-1.5">
                <x-multi-select
                    name="attendees"
                    :options="$users->map(fn ($u) => ['value' => $u->id, 'label' => $u->name, 'hint' => $u->roleLabel()])"
                    :selected="$selectedAttendees ?? []"
                    placeholder="Add attendees…" />
            </div>
        </x-field>
    </x-form-section>
</div>
