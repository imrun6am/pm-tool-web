## Why

Meeting minutes and daily notes often carry dates: "demo next Friday", "code freeze 20 Nov". Today someone has to copy that text into the calendar's Paste tab, or retype it. The user asked: "in meeting and notes, the calender events should also be extracted and and add events from there".

## What Changes

- **"Find calendar events" on a meeting note's page and on each daily note in the Notes feed.** It appears only when the `calendar_import` Claude feature is available. Daily notes have no page of their own, so the action sits on the note's card.
- **It reuses the existing import.** The action creates an ordinary `EventImport`, which goes through the same queued `ExtractCalendarEvents` job, review page, confirm step, history, retry and delete. There is no second extraction path or review screen. Nothing reaches the calendar until the person confirms.
- **The note's saved text is read.** The stored rich text is converted to plain text on the server, and link targets are kept so a meeting link can become the event's location.
- **The note's date resolves relative dates.** The extractor context gains an optional `written_on` date, taken from the meeting date or the daily note's date, so "next Friday" means the Friday after the note was written.
- **Server-side checks.** Only someone who can read the note can start an import from it. The feature must be available. A note with no text gets a clear message, with no import and no Claude call.
- **File and paste imports respect the same switch.** When `calendar_import` isn't available, anything that needs Claude is refused with a clear message, and `.ics` calendar data still imports. An import already queued, or retried, fails with that message instead of calling Claude.
- **The history says where an import came from**: "Meeting note: Sprint sync" or "Daily note, 3 Nov 2026", not a file name.

## Capabilities

### New Capabilities
- `calendar-import-from-notes`: starting a calendar import from a meeting note or daily note.

### Modified Capabilities

<!-- None: `calendar-file-import` has not been archived into openspec/specs yet. The review, confirm and privacy rules of that change apply unchanged, except for the one narrowing in design.md (an admin may not open an import of a note they cannot read). -->

## Impact

- **Backend:** `event_imports` gains `origin_type`, `origin_id` and `reference_date`. `EventImportService` gains `startFromNote` and `noteText`. `EventImportController` gains two actions, with two routes on the `calendar-import` rate limiter. `NotePolicy` gains `view`, and `EventImportPolicy` respects the source note.
- **Prompt:** `ClaudeEventExtractor` receives the written date and treats the source name as data.
- **Frontend:** a new `ai/panels/meeting-note-events` panel, a card action in `notes/_card`, and note wording in `calendar/imports/*`.
- **API:** none. Imports have no API endpoints, so there is nothing to mirror.
- **External:** one Claude call per import for notes under about 40k characters; note bodies are capped at 20,000 characters. The note text is sent to Anthropic.
