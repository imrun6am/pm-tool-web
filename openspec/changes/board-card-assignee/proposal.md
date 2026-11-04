## Why

A newly added card has no assignee and gets lost on a busy board. Nobody can list just the unassigned cards, and giving a card an owner means leaving the board for the task page.

## What Changes

- The board's Assignee filter gains an "Unassigned" option (`assignee_id=none`), on the web and in the API. It combines with the release and team filters and is kept in the URL, as the other filters are.
- A card's assignee becomes a control. Clicking it opens the app's searchable dropdown, where a user can pick someone, change the assignee or clear it. The card and its column update without a page reload.
- If the new assignee no longer matches the active assignee filter, or the task is no longer visible to the viewer, the card leaves the board.
- Two new endpoints on web and API: `GET board/tasks/{task}/assignees` lists who the viewer may pick for this card, and `PATCH board/tasks/{task}/assignee` sets or clears the assignee.
- Who may assign and who may be picked follow the existing rules, enforced on the server by the same validation the task form uses.
- The board's columns and cards get wider on laptop and desktop screens. On `lg` the column minimum goes from 12.5rem to 18rem, so columns that don't all fit scroll sideways inside the board. This reverses part of the compacting in `board-task-delete`, at the product owner's request.

## Capabilities

### New Capabilities
- `board-card-assignee`: the Unassigned board filter and assigning, changing or clearing a card's assignee from the board, with server-side limits and web/API parity.

### Modified Capabilities
<!-- None: `openspec/specs/` has no base specs yet. -->

## Impact

- **Backend**: `BoardService` (filter and `assign`), `TaskService` (assignable pool for one card), a new `AssignTaskRequest` built on `TaskRequest`, both `BoardController`s, `routes/web.php`, `routes/api/v1/collaboration.php`.
- **Frontend**: `board/index.blade.php`, `partials/board-card.blade.php`, a new `partials/board-assignee.blade.php`, `resources/js/searchable-select.js` (open and close events), `resources/css/app.css`.
- **API**: two additive endpoints and one new filter value, documented in `docs/api-v1.md`.
- **Data**: no schema change. `RecordsActivity` logs the change and `TaskEngagementObserver` notifies the new assignee, as for any task write.
- **Tests**: a new feature test file, plus cases in `WebApiParityTest`.
