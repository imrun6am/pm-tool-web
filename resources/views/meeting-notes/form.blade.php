<div class="card card-pad">
    @if ($meetingNote->event)
        <input type="hidden" name="event_id" value="{{ old('event_id', $meetingNote->event_id) }}">
        <p class="mb-6 flex items-center gap-2 rounded-lg bg-info-50 px-3 py-2 text-sm text-info-800 ring-1 ring-inset ring-info-600/15">
            <x-icon name="calendar" variant="outline" class="h-4 w-4 flex-none" />
            Linked to event: <span class="font-medium">{{ $meetingNote->event->title }}</span>
        </p>
    @endif

    <x-form-section title="Details" description="What the meeting was and when it happened.">
        <x-field label="Title" for="title">
            <input id="title" name="title" type="text" value="{{ old('title', $meetingNote->title) }}" required
                   placeholder="e.g. Sprint retro — action items" class="field-input" @invalid('title')>
        </x-field>

        <div class="grid gap-5 sm:grid-cols-2">
            <x-field label="Meeting type" for="type">
                <select id="type" name="type" class="field-select" @invalid('type')>
                    @foreach (\App\Models\MeetingNote::TYPES as $val => $label)
                        <option value="{{ $val }}" @selected(old('type', $meetingNote->type ?? \App\Models\MeetingNote::TYPE_DEFAULT) === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field label="Meeting date" for="meeting_date">
                <input id="meeting_date" name="meeting_date" type="date" required
                       value="{{ old('meeting_date', $meetingNote->meeting_date?->toDateString()) }}" class="field-input" @invalid('meeting_date')>
            </x-field>
        </div>

        <x-field label="Related release" for="release_id" optional hint="Leave as General for meetings that aren't about one release.">
            <select id="release_id" name="release_id" class="field-select" @invalid('release_id')>
                <option value="">General (no release)</option>
                @foreach ($releases as $r)
                    <option value="{{ $r->id }}" @selected((int) old('release_id', $meetingNote->release_id) === $r->id)>{{ $r->name }} ({{ $r->year }})</option>
                @endforeach
            </select>
        </x-field>
    </x-form-section>

    <x-form-section title="Access" description="Who was there, and who can read these notes.">
        <x-field label="Visibility" for="visibility" hint="“Attendees only” hides the note from everyone but its attendees, you, and team leads.">
            <select id="visibility" name="visibility" class="field-select sm:max-w-xs" @invalid('visibility')>
                @foreach (\App\Models\MeetingNote::VISIBILITIES as $val => $label)
                    <option value="{{ $val }}" @selected(old('visibility', $meetingNote->visibility ?? 'everyone') === $val)>{{ $label }}</option>
                @endforeach
            </select>
        </x-field>

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

    <x-form-section title="Notes" description="Discussion, decisions and action items. Formatting and lists are supported.">
        <x-field label="Meeting notes" for="meeting-note-body" name="body">
            <input id="meeting-note-body" type="hidden" name="body" value="{{ old('body', $meetingNote->body) }}">
            <trix-editor input="meeting-note-body" placeholder="Discussion, decisions, action items…" class="prose-notes min-h-[14rem]"
                         @invalid('body')></trix-editor>
            <x-ai-enhance input="meeting-note-body" kind="meeting" title-field="title" type-field="type" />
        </x-field>
    </x-form-section>
</div>
