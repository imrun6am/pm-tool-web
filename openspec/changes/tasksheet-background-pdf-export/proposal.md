## Why

`GET tasksheet/report.pdf` builds the whole PDF inside the web request: one query loads every row in the range, one Blade render turns them into a single HTML document, and dompdf lays that document out in memory. dompdf's memory grows much faster than the row count, so a team-wide export over a quarter, or an "all teams" export from a lead, can exhaust `memory_limit` or `max_execution_time` and take a PHP worker down with it. The same request also blocks the browser for the whole render.

## What Changes

- **Exports are queued.** Clicking *Export PDF* creates a `tasksheet_exports` record (`pending → processing → ready | failed`) and returns at once. Nothing is rendered in the request.
- **Generation is chunked.** The service plans the export as a list of bounded chunks — consecutive days packed up to a row budget, and an oversized single day split by member — and a queued batch renders one chunk per job into a partial PDF on the private `local` disk. A final job merges the parts with FPDI. No job ever holds more than one chunk of rows or renders more than one chunk of HTML.
- **Progress is tracked** as rendered chunks out of total (plus the merge step), so the UI can show a percentage.
- **Failure is handled.** Jobs have explicit `timeout`, `tries` and `backoff`; a final failure marks the export failed with the error message and deletes every partial file. A failed export can be retried.
- **An Exports page** (`/tasksheet/exports`) lists the user's own exports with the filters used, requester and time, a status badge, a live progress bar, and Download / Retry / Delete. It polls a small JSON status endpoint while anything is pending, so no manual refresh is needed. The page also hosts the export form, prefilled from the filters the user came from.
- **The old URL keeps working.** `GET tasksheet/report.pdf` (still linked from KPI review spreadsheets) enforces the same access rule and redirects to the Exports page with its filters prefilled, instead of rendering.
- **Retention.** Exports older than 14 days are pruned along with their files.

## Capabilities

### Modified Capabilities

- `team-tasksheet`: the PDF report becomes a queued, chunked export with a per-user exports list, live progress, and requester-only download.

<!-- Delta specs are written as ADDED requirements, matching the repo convention: openspec/specs/ has not been synced, so there is no requirement text for a MODIFIED block to amend. -->

## Impact

- **Dependency**: `setasign/fpdi` + `setasign/fpdf` (pure PHP, maintained by Setasign) to merge the partial PDFs.
- **Database**: new `tasksheet_exports` table.
- **Backend**: `TasksheetExport` model, `TasksheetExportPolicy`, `TasksheetExportService`, `RenderTasksheetExportChunk` and `MergeTasksheetExport` jobs, `TasksheetExportController`, `config/tasksheet.php`. `TasksheetService` gains a shared `reportQuery()` and `includesFeedbackFor()` so the export cannot drift from the existing report rules.
- **UI**: new `tasksheet/exports` page; the tasksheet and per-user Export actions now submit to it.
- **Queue**: the user's `queue:work` worker must be running for exports to complete.
- **API**: unchanged. `GET /api/v1/tasksheet/report` still renders synchronously (see Non-Goals).

## Non-Goals

- The API report endpoint is not converted here. Queued exports over the API need their own resource shape and polling contract for the desktop client; that is a follow-up change.
- No emailed or scheduled exports, and no formats other than PDF.
- No change to who may export what, or to when lead feedback is included.
