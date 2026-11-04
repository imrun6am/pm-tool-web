## 1. Backend

- [x] 1.1 Add `TaskPolicy::deleteFromBoard(User, ?Task)`: admin only; with a task, also visible and top-level
- [x] 1.2 Add `BoardService::deleteCards()` and `MAX_BULK_DELETE`: one transaction, Eloquent deletes, returns deleted ids and subtask count
- [x] 1.3 Add `BulkDeleteTasksRequest`: role check first, validate every id, then authorize each loaded task
- [x] 1.4 Add `destroy` and `bulkDestroy` to the web `BoardController`, returning JSON
- [x] 1.5 Add `destroy` and `bulkDestroy` to the API `BoardController`, with a message
- [x] 1.6 Add the web and API routes

## 2. Board UI

- [x] 2.1 Add the checkbox and delete form to `partials/board-card`, for admins only, with a counted confirmation
- [x] 2.2 Add the bulk bar: select all with count, active filter labels, archive note, selected count, clear, delete
- [x] 2.3 Add the board script: selection sync, bulk confirmation text, fetch on confirmed submit, in-place removal, failure toasts
- [x] 2.4 Make the Archive column report toggles, so select all follows what is shown
- [x] 2.5 Add the card selected and delete-button styles
- [x] 2.6 Compact the top section: filters beside the title, one slim summary and bulk row, tighter full-height header
- [x] 2.7 Make the columns share the width on `lg`, with a 12.5rem minimum

## 3. Docs and tests

- [x] 3.1 Document the endpoints and the role matrix row in `docs/api-v1.md`
- [x] 3.2 Feature tests: single delete, bulk delete, select all with a filter, every non-admin role rejected, invalid ids, controls hidden
- [x] 3.3 Add a bulk delete case to `WebApiParityTest`
- [x] 3.4 Run the full PHPUnit suite and Pint
- [x] 3.5 Check the UI in a browser against a scratch SQLite database
