## Why

Every task has to belong to a release (`tasks.release_id` is required). People want to capture work on the board straight away, such as a bug, a chore or something from a stand-up, without first deciding which release it ships in, and work outside any release has nowhere to go. The board quick-add forces a release choice, and the tasksheet "Add to board" needs an open release before it works at all.

## What Changes

- **Schema:** `tasks.release_id` becomes nullable. Deleting a release now **unlinks** its tasks (`nullOnDelete`) instead of deleting them. **BREAKING** for anyone who relied on the release-delete cascade.
- **Creating without a release:**
  - the board quick-add gets an optional release picker that defaults to "No release";
  - the API `POST /board/tasks` accepts `release_id: null`;
  - a new `POST /api/v1/tasks` creates a standalone task with full attributes and an optional `release_id`.
- **Link, change or unlink later:** the task page gets a "Release" property. The task update (web and API) accepts `release_id` (`null` unlinks it), and a top-level task's subtasks follow its release. A subtask always inherits its parent's release, or none.
- **Board:**
  - cards show the release chip only when a release is linked;
  - the release filter gains a "No release" option (`release_id=none`), on the web and API board and on the API task index.
- **Assignees:**
  - an unlinked task can be assigned to any active user;
  - a linked task keeps the "release team members only" rule, checked against the release being linked.
- **Tasksheet "Add to board":** the board-release picker gains "No release". With it, the plan's tasks are created unlinked and deduplicated against the member's unlinked tasks. The button no longer needs an open release.
- **Unchanged:**
  - release detail pages and the release task panel still show and create only that release's tasks;
  - release, project and dashboard progress roll-ups ignore unlinked tasks;
  - activity for an unlinked task is logged with a `null` release.

## Capabilities

### Modified Capabilities
- `task-kanban-board`: tasks no longer require a release. This covers board creation, the "No release" filter, release linking and unlinking, and card display.

## Impact

- **Migration:** `2026_11_01_100001_make_tasks_release_id_nullable.php`. It drops and re-adds the FK with `nullOnDelete`; SQLite rebuilds the table.
- **Backend:**
  - `TaskService`: `create`, `release_id` writable, subtask inheritance, `assignableUsers`;
  - `BoardService` (`quickAdd` takes `?Release`, plus the `none` filter);
  - `TaskRequest`;
  - the web and API `BoardController` and `TaskController`;
  - `PlanToBoardService`, `TasksheetBoardRequest`, `TasksheetController::addToBoard`.
- **API:**
  - `release_id` and `release` are nullable in `TaskResource` and `TaskSummaryResource`;
  - new `POST /api/v1/tasks`;
  - `docs/api-v1.md`.
- **Views:** `board/index`, `partials/board-card`, `tasks/show`, `tasksheet/_row`, and the release delete confirmation.
- **Access:** unchanged. Task routes are open to every signed-in user, like the global board, so an unlinked task is visible to everyone, Developer and QA included.
