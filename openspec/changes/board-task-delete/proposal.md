## Why

Boards collect stale cards: test tasks, abandoned work, imports that went wrong. Clearing them today means opening each task and deleting it from its page, one at a time. Admins need to clean up a board directly, and to clear many cards at once, without leaving it.

## What Changes

- An admin sees a delete button and a selection checkbox on every board card.
- A bulk bar above the columns offers "select all" for the cards currently shown and deletes the selected cards together. The bar names the active filters, and says when the collapsed Archive column is left out, so it's clear what select all covers.
- Every delete asks for confirmation first, stating how many tasks will go and how many subtasks and comments go with them.
- New admin-only endpoints on web and API: `DELETE board/tasks/{task}` and `POST board/tasks/bulk-delete`. Other roles get no controls and a 403.
- The board removes the cards and updates its counts in place, with no page reload.
- The board's top section is compacted so laptop screens keep their height for cards: the filters move up beside the title, the summary and bulk bar share one slim row, the drag hint text goes, and on `lg` screens the columns share the available width instead of each holding a fixed 19rem.
- **BREAKING**: the existing `DELETE tasks/{task}` route also becomes admin-only, by the product owner's decision. Other roles lose the delete buttons on the task page and a release's task panel, and get a 403 from the route on web and API, for tasks and subtasks alike.

## Capabilities

### New Capabilities
- `board-task-delete`: admin-only deletion of board cards, single and in bulk, with select all over the filtered board, a counted confirmation, in-place updates and web/API parity.

### Modified Capabilities
<!-- None: `openspec/specs/` has no base specs yet, and no existing requirement changes. -->

## Impact

- **Backend**: `TaskPolicy` (new `deleteFromBoard` ability), `BoardService` (new `deleteCards`), a new shared `BulkDeleteTasksRequest`, both `BoardController`s, `routes/web.php`, `routes/api/v1/`.
- **Frontend**: `board/index.blade.php`, `partials/board-card.blade.php`, `layouts/app.blade.php` (tighter header padding on full-height pages, which only the board uses), `resources/css/app.css`.
- **API**: two additive endpoints, documented in `docs/api-v1.md`.
- **Data**: no schema change. The model's existing `deleting` hook removes subtasks and comments, and `RecordsActivity` logs each deletion. Tasks have no attachments of their own.
- **Tests**: a new feature test file, plus a case in `WebApiParityTest`.
