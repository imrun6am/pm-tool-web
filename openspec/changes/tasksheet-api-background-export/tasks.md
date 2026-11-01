## 1. HTTP surface

- [x] 1.1 `TasksheetExportResource` (V1): status fields matching the web status JSON, API `download_url`, selection, timestamps (design decision 5)
- [x] 1.2 `Api\V1\TasksheetExportController`: index (with capacity meta), store (202 + `Location`), status, show, download, retry (202), destroy — thin, service- and policy-backed
- [x] 1.3 Routes `api-v1.tasksheet.exports.*` in `routes/api/v1/tasksheet.php`
- [x] 1.4 `Api\V1\TasksheetController::report` answers 410 with a `Link` to the export endpoint; drop its dompdf render (decision 3)

## 2. Docs

- [x] 2.1 `docs/api-v1.md`: export endpoints, polling loop, status codes, retired report endpoint

## 3. Tests

- [x] 3.1 `tests/Feature/Api/V1/TasksheetExportTest.php`: create queues without rendering; end-to-end on the sync queue to a downloadable PDF; status and list scoped to the requester; member may export only own rows; requester-only show/download/retry/delete; capacity 422; retry of a non-failed export 422; download before ready 404; old report 410 without creating anything
- [x] 3.2 `WebApiParityTest`: the same export reports the same status fields on both surfaces
- [x] 3.3 Replace the old "API report returns a PDF" assertion in `TasksheetStandupAndReportTest`
- [x] 3.4 `php artisan test` and `vendor/bin/pint --test` pass (597/599 on 2026-11-01: the 2 failures are board task creation, from a sibling session’s in-progress board work, not this change)
