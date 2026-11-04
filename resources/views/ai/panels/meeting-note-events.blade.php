{{--
    AI on a meeting note: find the dates in it and propose calendar events.
    The page has already checked the viewer may read this note; the server checks again on submit.
    @include('ai.panels.meeting-note-events', ['meetingNote' => $meetingNote])
--}}
@if (app(\App\Services\Ai\AiFeatures::class)->available('calendar_import'))
    <section class="card" aria-labelledby="ai-events-{{ $meetingNote->id }}">
        <div class="card-header flex items-center gap-2">
            <x-icon name="calendar" variant="outline" class="h-4 w-4 text-violet-600" />
            <h2 id="ai-events-{{ $meetingNote->id }}" class="card-title">Dates → calendar</h2>
        </div>
        <div class="px-5 pb-5 text-sm">
            @if (\App\Services\Calendar\EventImportService::noteText($meetingNote->body) === '')
                <p class="text-slate-500">This meeting has no written notes yet, so there are no dates to read.</p>
            @else
                <p class="text-slate-500">AI looks for meetings, deadlines and releases in these minutes. You choose which to add; nothing reaches the calendar until you confirm.</p>
                <form method="POST" action="{{ route('meeting-notes.calendar-events', $meetingNote) }}" class="mt-3"
                      x-data="{ tz: Intl.DateTimeFormat().resolvedOptions().timeZone || '', busy: false }" @submit="busy = true">
                    @csrf
                    <input type="hidden" name="timezone" :value="tz">
                    <x-ai-button label="Find calendar events" busy-label="Starting…" click="$el.form.requestSubmit()" />
                </form>
            @endif
        </div>
    </section>
@endif
