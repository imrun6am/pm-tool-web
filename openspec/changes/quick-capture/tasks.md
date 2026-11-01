## 1. Backend

- [x] 1.1 `TaskSearchService` (visibility-scoped, escaped LIKE, open first)
- [x] 1.2 `QuickCaptureService` pickers (assignable people, ongoing releases per viewer)
- [x] 1.3 `QuickTaskRequest`, plus `QuickCaptureController` (create page, store: JSON or redirect)
- [x] 1.4 `TaskSearchController` (web JSON) and `Api\V1\TaskSearchController`, with routes
- [x] 1.5 "New task" in `Navigation::createActions`

## 2. UI

- [x] 2.1 `tasks/quick` page and `layouts/quick-capture` dialog plus shortcut sheet (included in `layouts/app`)
- [x] 2.2 `engagement.js`: `C` / `?` keys, intercept `/tasks/new`, submit via fetch, toast, refresh board/dashboard
- [x] 2.3 Command palette: Tasks group with keyboard navigation

## 3. Verification

- [x] 3.1 Feature tests: create (JSON and redirect), scoping, search visibility and order, API parity
- [x] 3.2 `docs/api-v1.md`; browser check
