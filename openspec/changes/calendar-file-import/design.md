## Context

The calendar already has `Event`, `EventService` (all-day normalization) and `EventPolicy`. Claude integrations follow one pattern:
- an interface bound in `AppServiceProvider` (faked in tests);
- `config('services.anthropic.*')`;
- `MissingApiKeyException` when no key is set;
- `outputConfig.format` json_schema with `fallbacks: 'default'`;
- checks on the `refusal` and `max_tokens` stop reasons.

Long work runs as queued jobs (KPI reports).

## Decisions

**Proposals live on the import row (JSON), not in a table.** They are a draft that only one user edits. Keeping them on `event_imports.proposals` means nothing touches `events` until confirm. Each proposal records `event_id` once it is created, so reopening a completed import can't add it twice.

**Source routing.**
- `.ics`: `IcsParser`. It handles unfolding, `DTSTART`/`DTEND` with `VALUE=DATE`, UTC `Z`, `TZID`, and `SUMMARY`/`DESCRIPTION`/`LOCATION`/`ATTENDEE`, with RFC 5545 text unescaping. A file with no `VEVENT` falls back to Claude, read as text.
- PDF and images: the Files API upload is streamed from disk, and a `document`/`image` block references it by `file_id`. This keeps the worker's memory flat and the request small. The uploaded file is deleted in `finally`.
- docx/xlsx/csv/txt/md: `DocumentTextConverter` produces plain text, capped at 1,000,000 characters (it fails clearly rather than truncating silently). `TextChunker` splits it at paragraph or line boundaries into chunks of about 40k characters.

**Times are wall-clock in the uploader's timezone.** The event form stores naive local times. The upload form sends the browser's IANA timezone, which falls back to `app.timezone`. Claude is told to convert explicitly zoned times into that zone. The ICS parser does the same.

**Chunk-resumable job.** `chunks_done` and the proposals collected so far are saved after each chunk. A retry (3 tries, backoff 60/300 s, 900 s timeout) skips finished chunks. Configuration and request errors (missing key, 401/403/400) fail at once. `failed()` records the message.

**Matching happens when processing finishes.**
- Attendees are matched in this order: exact email, then case-insensitive full name, then a unique first name.
- Releases are matched by exact case-insensitive name, then a unique substring.

The results are stored with the proposal (`attendee_ids`, `unmatched_attendees`, `release_id`), and the review form can change them.

**Authorization.**
- Uploading follows event creation: any signed-in user.
- `EventImportPolicy`: `view` and `delete` are allowed for the uploader or an admin. `confirm` and `retry` are uploader-only, because confirmed events are attributed to the uploader.

## Risks

- A PDF over Claude's page limit fails with the API's 400 message, recorded on the import.
- Large text files make several paid calls. Uploads are rate-limited per user (10 per hour).

**Pasted text is stored as a file.** It is not kept in a text column. Storing it as a file means the job, chunking, ICS routing, retry and delete (file clean-up) need no new branch: a paste is just a `.txt` or `.ics` upload that the server wrote. Two small columns serve the history list without reading files:
- `input`: `file` or `paste`;
- `preview`: about 160 characters, or the event titles for pasted iCalendar data.

The 100k-character cap keeps a paste to at most three chunks.
