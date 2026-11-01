## 1. Backend

- [x] 1.1 Config `services.anthropic.calendar_import_model` / `calendar_import_effort` (+ `.env.example`)
- [x] 1.2 `event_imports` migration, `EventImport` model, `EventImportPolicy`
- [x] 1.3 `IcsParser`, `DocumentTextConverter` (docx/xlsx/csv/txt), `TextChunker`
- [x] 1.4 `EventExtractor` interface + `ClaudeEventExtractor` (Files API for PDF/images, json_schema, fallback, stop checks), bound in `AppServiceProvider`
- [x] 1.5 `EventImportService` (start, process with chunk resume, normalize/dedupe, matching, review rows, confirm via `EventService`, delete)
- [x] 1.6 `ExtractCalendarEvents` job (tries/backoff/timeout, fail-fast on config errors, `failed()` records error)
- [x] 1.7 `EventImportController`, requests, routes, `calendar-import` rate limiter

## 2. Frontend

- [x] 2.1 `calendar/imports/index`: upload card (drag & drop, timezone) + history
- [x] 2.2 `calendar/imports/show`: status with polling, editable review list, confirm
- [x] 2.3 "Import" button in the calendar header

## 3. Tests & Verification

- [x] 3.1 Feature tests with a fake extractor (upload queues the job, the job stores proposals, chunking, confirm, policy, failure and missing key, ICS without Claude)
- [x] 3.2 `php artisan test` and `vendor/bin/pint --test` pass

## 4. Paste text (addendum)

- [x] 4.1 `input` + `preview` columns; `EventImportService::startFromText` (HTML → text, ICS detection, store as file)
- [x] 4.2 `EventImportTextRequest` (1–100k chars) + `POST calendar/imports/text`
- [x] 4.3 Upload / Paste tabs with live count; history and import page show "Pasted text" + preview
- [x] 4.4 Tests (queues job, empty/over-limit rejected, HTML stripped, pasted ICS uses parser, privacy, delete cleans up)
