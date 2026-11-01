## 1. Data & Dependencies

- [x] 1.1 `composer require setasign/fpdi setasign/fpdf` (design decision 4)
- [x] 1.2 Migration `tasksheet_exports`: requester, team, member, date range, filters JSON, `include_feedback`, status, `chunks_total`/`chunks_done`, `rows_total`, `batch_id`, `file_path`, `error`, `started_at`/`completed_at`
- [x] 1.3 `TasksheetExport` model: status constants, `progressPercent()`, `isRunning()`/`isReady()`/`isFailed()`, labels, `Prunable` after 14 days with file cleanup
- [x] 1.4 `config/tasksheet.php`: `export.chunk_rows`, `export.max_running`, `export.retention_days`

## 2. Generation

- [x] 2.1 `TasksheetService::reportQuery()` shared by `reportRows()` and the export; `includesFeedbackFor()` shared by `reportData()` and the export
- [x] 2.2 `TasksheetExportService::plan()`: day-count packing plus oversized-day member split (decisions 1–2)
- [x] 2.3 `start()` / `retry()`: create or reset the record, plan, dispatch the batch with the merge in `then`
- [x] 2.4 `RenderTasksheetExportChunk` (Batchable; tries/backoff/timeout; failed() → mark failed + clean up)
- [x] 2.5 `MergeTasksheetExport` (FPDI; single-part shortcut; failed() → mark failed + clean up)
- [x] 2.6 Report template: header only on part 1, export-wide row count and generation time

## 3. Access & HTTP

- [x] 3.1 `TasksheetExportPolicy`: `create` (lead or self), `view`/`download`/`retry`/`delete` (requester only; download re-checks scope and feedback)
- [x] 3.2 `TasksheetExportController`: index, store, status (JSON), download, retry, destroy — thin, service-backed
- [x] 3.3 Routes; old `tasksheet.report` redirects to the exports page with a prefill after the same authorization
- [x] 3.4 Schedule `model:prune` daily

## 4. UI

- [x] 4.1 `tasksheet/exports` page: export form (prefilled), exports table, status badge, progress bar, Download/Retry/Delete with the confirm dialog
- [x] 4.2 `resources/js/tasksheet-exports.js` Alpine poller (Turbo-safe destroy, pauses when hidden, toasts on completion)
- [x] 4.3 Point the daily sheet and per-user Export actions at the store route

## 5. Tests

- [x] 5.1 Store dispatches instead of rendering (`Queue::fake()`); authorization parity incl. 403s
- [x] 5.2 Job produces a merged file; chunking across a large dataset and an oversized day
- [x] 5.3 Status JSON shape and scoping; download/retry/delete authorization
- [x] 5.4 Failure marks failed, records the error, removes partial files; retry resets
- [x] 5.5 Update the old synchronous report tests to the redirect behaviour
- [x] 5.6 `php artisan test` and `vendor/bin/pint --test` pass
