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

## 6. Team on Tasks (follow-up)

- [x] 6.1 Migration `2026_11_01_100002_add_team_id_to_tasks_table`: nullable `team_id` FK (`nullOnDelete`), backfilled from the releases. `down()` refuses while unlinked tasks have a team.
- [x] 6.2 `Task::team()`; `TaskService::teamFor()` (release team, then chosen team, then the assignee's single team); create, update and subtask rules; `Release` `updated` hook resyncs teams.
- [x] 6.3 `team_id` filter (`none` included) on the board (web and API) and the API task index, through `ReadsTaskFilters::idOrNoneFilter()` and `TaskService::whereIdOrNone()`.
- [x] 6.4 Board Team filter; quick-add takes the filtered team. Task page Team property (editable only when unlinked). `team_id` and `team` in the API resources and docs.
- [x] 6.5 Tests: combined filters, the no-team filter, defaults, sync on release team change, and limited roles.

## 7. Team Scoping for Developers and QA (follow-up)

- [x] 7.1 `Task::scopeVisibleTo` / `isVisibleTo`, `User::currentTeamIds` / `scopeTeammatesOf`, `TaskPolicy` (view/update/delete/create with a release).
- [x] 7.2 Services scope by viewer: `BoardService::columns`/`move`, `TaskService::filtered`/`forRelease`/`assignableUsers`. Release show (web and API) scopes `rootTasks` and `tasks_count`.
- [x] 7.3 Authorization on web and API task show/update/status/delete/subtasks, task comments and board move. `TaskRequest::authorize` uses the policy.
- [x] 7.4 `KeepsTasksInViewerTeams` concern (pinned team, own releases, own teammates, multi-team pick) in `TaskRequest` and a new `BoardTaskRequest`.
- [x] 7.5 Board: Team filter hidden for single-team members; releases, teams and assignees limited; multi-team quick-add team select. Task page: team read-only unless multi-team; release and team pickers keep the current values.
- [x] 7.6 `Task::saving` keeps a linked task's team equal to its release's.
- [x] 7.7 `TaskTeamScopingTest`; existing dev/QA tests now put the developer on the release's team.
