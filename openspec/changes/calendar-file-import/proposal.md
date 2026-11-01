## Why

Release plans, agendas, meeting invites and schedules already exist as files. Re-typing each date into the calendar is slow and error-prone. Users want to upload the file, let Claude find the events, check them, and add them in one go.

## What Changes

- **"Import from file" on the calendar.** A new page, `calendar/imports`, takes one upload: PDF, image (PNG/JPG/GIF/WebP), `.ics`, `.docx`, `.xlsx`, `.csv`, `.txt` or `.md`, up to 20 MB (images up to 5 MB). The request stores the file on the private `local` disk, creates an import record and queues a job. It returns at once.
- **Background extraction** (`ExtractCalendarEvents` job):
  - `.ics` is parsed deterministically, with no Claude call. Claude is used only when the calendar has no readable `VEVENT`.
  - PDFs and images are sent to Claude natively through the Files API, so the job never base64-encodes a 20 MB file in memory.
  - Other formats are converted to text (docx via its XML, xlsx/csv via openspout) and split into chunks of about 40k characters. Each chunk is one Claude call, and progress is saved per chunk so a retry resumes where it stopped.
  - Claude returns a strict JSON schema of events. The prompt gives today's date, the user's timezone and the known release names, and treats the document as data, never as instructions.
- **Review.** The import moves from pending → processing → needs_review. The page polls while it runs. When it is ready, it shows an editable list of proposed events. For each one the user can:
  - include or exclude it;
  - edit the title, type, dates and times, all-day, location, description, attendees and release.

  Attendees are matched to users by email or name, and unmatched names are flagged. Release hints are matched to releases. Rows that look like events already on the calendar, or that have low confidence, start unticked.
- **Confirm.** Ticked rows are created through `EventService`, and the import becomes completed. Nothing is written to `events` before this step. A completed import can be reopened to add rows that were skipped; rows already added are locked.
- **History** of the user's imports, with open/review, retry (when failed) and delete.
- **Config:** `services.anthropic.calendar_import_model` / `calendar_import_effort` (`CALENDAR_IMPORT_MODEL`, `CALENDAR_IMPORT_EFFORT`).

## Capabilities

### New Capabilities
- `calendar-file-import`: uploading a file, extracting events in the background, reviewing them and confirming them into calendar events.

### Modified Capabilities

<!-- None (no archived specs). -->

## Impact

- **Backend:**
  - an `event_imports` table and the `EventImport` model, with `EventImportPolicy`;
  - `App\Services\Calendar\*`: `EventExtractor` + `ClaudeEventExtractor`, `DocumentTextConverter`, `IcsParser`, `TextChunker` and `EventImportService`;
  - the `ExtractCalendarEvents` job;
  - `EventImportController`, `EventImportRequest` and `ConfirmEventImportRequest`;
  - routes and a `calendar-import` rate limiter.
- **Frontend:** `calendar/imports/index` and `calendar/imports/show`, plus an "Import" button in the calendar header.
- **External:** one or more Claude calls per non-`.ics` import. The document is sent to Anthropic, and PDFs and images are uploaded to the Files API and deleted after use.
- **Ops:** the job's timeout is 900 s, so `DB_QUEUE_RETRY_AFTER` must stay above it (it is already 960 for KPI reports). Restart the worker after deploying.
