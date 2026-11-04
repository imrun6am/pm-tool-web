## 1. Backend

- [x] 1.1 `request_import` in `config/ai.php`, `request-import` rate limiter
- [x] 1.2 `triage_imports` table, `triage_items.triage_import_id`, `TriageImport`, `TriageImportPolicy`, `TriageItemPolicy::viewSource`
- [x] 1.3 `RequestExtractor` through `AiGateway` (native files, text chunks, data-not-instructions prompt)
- [x] 1.4 `TriageImportService` (start, process with resume, normalize, review rows, confirm via `TriageService`, retry, delete)
- [x] 1.5 `ExtractTriageRequests` job
- [x] 1.6 `TriageItemRequest::fields()` shared with `ConfirmTriageImportRequest`; upload and text requests

## 2. Web and API

- [x] 2.1 `TriageImportController`, routes, source download
- [x] 2.2 `Api\V1\TriageImportController`, `TriageImportResource`, routes, `docs/api-v1.md`

## 3. Frontend

- [x] 3.1 Import card on the Requests page: drop, browse, paste a screenshot, paste text
- [x] 3.2 Open imports list in `<x-poll-region>`
- [x] 3.3 Review page: running, failed, empty and review states
- [x] 3.4 Source links on the queue and on What you've sent

## 4. Tests and verification

- [x] 4.1 `RequestImportTest` with `FakeAiTransport`: files, images, text, several requests, review, permissions, feature off, failures, API
- [x] 4.2 Full suite and Pint
- [x] 4.3 Browser check
