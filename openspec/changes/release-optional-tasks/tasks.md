## 1. Schema

- [x] 1.1 Migration `2026_11_01_100001_make_tasks_release_id_nullable`: drop the FK, make `release_id` nullable, re-add the FK with `nullOnDelete`. `down()` restores the cascade and refuses while unlinked tasks exist. Verified on SQLite (up and down) and with MySQL `--pretend`.
- [x] 1.2 `Release` `deleting` hook unlinks tasks instead of deleting them; the release delete confirmation says so.

## 2. Services and Requests

- [x] 2.1 `TaskService`:
  - `create()` with an optional release, and `createForRelease()` delegating to it;
  - `release_id` writable, applied on update only when present;
  - subtasks follow their parent's release;
  - `NO_RELEASE` filter, `whereRelease()`, `nextPosition()`;
  - `assignableUsers()` falls back to all active users.
- [x] 2.2 `BoardService`: `quickAdd(?Release)`, and the `none` filter in `columns()`.
- [x] 2.3 `TaskRequest`: nullable `release_id`; the assignee-on-team rule checks against the release being linked, the parent's release for subtasks, or nothing.
- [x] 2.4 `PlanToBoardService` takes `?Release` and dedupes against unlinked tasks. `TasksheetBoardRequest`'s `board_release_id` is nullable, and a chosen release must still be open and on the team.
- [x] 2.5 `Activity::$fillable` gains `release_id`. Release history was silently dropping it.

## 3. Controllers, API and Docs

- [x] 3.1 `ReadsReleaseFilter` concern (`release_id=none`), used by the web board, API board and API task index.
- [x] 3.2 Web and API board `storeTask`: `release_id` nullable.
- [x] 3.3 API: new `POST /tasks` (`TaskController@store`); the release route moves to `storeForRelease`.
- [x] 3.4 `docs/api-v1.md`: new routes and a "Tasks without a release" section.

## 4. UI

- [x] 4.1 Board: "No release" filter option; the quick-add release picker is optional and defaults to "No release".
- [x] 4.2 Board card: release chip only when linked, neutral stripe otherwise.
- [x] 4.3 Task page:
  - a Release property (a select for top-level tasks, read-only for subtasks);
  - a header release link;
  - the breadcrumb falls back to Board.
- [x] 4.4 Tasksheet drawer: "No release" option; the button no longer needs an open release.

## 5. Tests

- [x] 5.1 `ReleaseOptionalTasksTest` covers:
  - web and API creation without a release;
  - the release route pinning its release;
  - subtask inheritance;
  - link and unlink (web and API, partial update);
  - assignee rules;
  - the "No release" filter (web board, API board, API tasks);
  - release pages and roll-ups;
  - the member dashboard;
  - limited-role visibility;
  - activity with a null release;
  - release delete unlinking.
- [x] 5.2 Updated `KanbanBoardTest` (validation) and `TasksheetPlanToBoardTest` (no-release drawer, plus add-to-board without a release with dedupe).
- [x] 5.3 `php artisan test` and `vendor/bin/pint --test` are green for this change's files.
