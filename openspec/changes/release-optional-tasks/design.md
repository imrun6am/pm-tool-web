## Context

`tasks.release_id` is a required FK with `cascadeOnDelete` (`2026_07_22_110002_create_tasks_table.php`). Code reads `$task->release->…` in four places:
- the board card;
- the task page breadcrumb;
- `TaskService::assignableUsers`;
- `TaskRequest`'s assignee-on-team rule.

Everything else already treats the release as a relation that can be filtered on. That includes the roll-ups, which use `whereIn('release_id', …)`, and `Release::tasks()` / `rootTasks()`. Task routes sit outside the `full-access` group: every signed-in user can see and edit any task, and there is no per-task policy.

## Goals / Non-Goals

**Goals:**
- create a task with no release from the board, the API and the tasksheet;
- link, change or unlink a release later;
- nothing errors on a `null` release.

**Non-Goals:**
- a standalone "new task" page on the web (the board quick-add plus the task page cover it);
- projects or teams on tasks without a release;
- per-task access control.

## Decisions

### 1. `nullOnDelete` instead of cascade
Tasks are now first-class work items. Deleting a release (a planning record) should not silently destroy the work and the discussion attached to it. With `nullOnDelete`, the tasks drop back onto the board unlinked, where they can be relinked or archived. The release delete confirmation says so.

*Alternative:* keep the cascade. It is consistent with the old model, but it makes a planning edit destructive to work that may have existed before the release was linked.

### 2. Migration
The migration drops the FK, changes the column with `nullable()->change()`, and re-adds the FK with `nullOnDelete()`. On SQLite, Laravel rebuilds the table for both steps. On MySQL, they run in place, and the `(release_id, parent_id)` index still backs the FK.

`down()` restores the cascade FK and refuses to restore `NOT NULL` while unlinked tasks exist. It throws rather than deleting them.

### 3. `release_id` is writable, but only on top-level tasks
`TaskService::WRITABLE` gains `release_id`.
- **Creating:** `createForRelease` and `createSubtask` still override it, so the release route pins a task to that release and a subtask always takes its parent's release.
- **Updating a subtask:** `update()` drops `release_id`.
- **Updating a top-level task:** a new `release_id` is copied to its subtasks in the same transaction, quietly, with no per-subtask activity rows.
- **Partial updates:** the key is only applied when present, so an API `PUT` without `release_id` leaves the link alone.

### 4. "No release" filter value
The board and the API task index accept `release_id=none`, which maps to `whereNull('release_id')`. The integer form is unchanged.

### 5. Assignees
- With a release (from the route, the request's `release_id`, or the task's current release), the assignee must be on that release's team. An unchanged assignee is still allowed.
- With no release, any existing user may be assigned. `assignableUsers` then lists all active users.

### 6. Tasksheet without a release
`board_release_id` becomes nullable. When it is empty, `PlanToBoardService` creates unlinked tasks and dedupes against the member's unlinked top-level tasks. A chosen release must still belong to the row's team and be open.

### 7. A task's team is stored, not derived
A task's team used to come only from `release->team`, which an unlinked task doesn't have. There were three options:
- (a) store an optional `tasks.team_id`;
- (b) derive the team from the release or the assignee's current membership;
- (c) match a team filter on either the release's team or the assignee's membership.

We chose (a). It is explicit, it stays stable when people change teams, and it filters with a plain indexed `where`. Options (b) and (c) make a card's team change as membership changes, and they can't express "this work belongs to team X" for an unassigned task.

The rules for `team_id`:
- **Linked tasks:** always the release's team. `TaskService::update` forces it, and the `Release` `updated` hook resyncs it when a release changes team.
- **Unlinked tasks:**
  - on create, the team comes from the request, else from the assignee's single current team, else none;
  - on update, the team changes only when `team_id` is sent;
  - unlinking keeps the existing team.
- **Subtasks:** they copy the parent's team, and follow it alongside `release_id`.
- **Board quick-add:** under a team filter, the new card takes that team. Tasksheet add-to-board uses the row's team.

### 8. Developers and QA see only their own teams' tasks
The user asked for this explicitly: Developers and QA don't choose a team, and they don't see other teams' work.

**Visibility:** `Task::scopeVisibleTo($user)` / `isVisibleTo()` let a limited user see:
- tasks whose `team_id` is one of their current teams (`team_user.left_at` null);
- tasks assigned to them, from any team;
- teamless tasks they created (personal tasks).

Leads and viewers are unrestricted.

**Enforcement:**
- **Lists:** the scope is applied in the services by viewer: `BoardService::columns`/`move`, `TaskService::filtered`/`forRelease`/`assignableUsers`, and the release pages' task lists and counts.
- **Direct URLs:** `TaskPolicy` (`view`/`update`/`delete`/`create(?Release)`) guards show, update, status, delete, subtasks, task comments and board moves, on the web and API. Anything else is a 403.

**Team choice:** the `KeepsTasksInViewerTeams` request concern, used by `TaskRequest` and the new shared `BoardTaskRequest`, handles the team for limited users.
- **One team:** `team_id` is pinned to that team on create, and on edit when one is sent. A submitted team is ignored.
- **Several teams:** they must pick one of their own, unless a release decides it.
- **No team:** the task stays teamless (personal) instead of being refused, so a newcomer can still track their own work.
- **Releases:** only their teams' releases can be linked.
- **Assignees:** only teammates or themselves.
- **Edits:** a task's unchanged release, team or assignee always passes, so a task from another team that is assigned to them stays editable without being moved.

**UI:**
- the board hides the Team filter for single-team members and limits options, releases and the assignee list to their teams;
- quick-add asks multi-team members which team a card is for;
- the task page shows the team read-only unless they are on several teams.

**Model invariant:** `Task::saving` copies the release's team whenever `release_id` changes, so seeders and direct `Task::create` calls keep linked tasks consistent.

## Risks / Trade-offs

- **Losing the cascade.** Deleting a release no longer removes its tasks, so the board can collect unlinked leftovers. The board's "No release" filter makes them easy to find and archive.
- **Activity history.** Activity for an unlinked task has `release_id = null`, so it doesn't appear in any release's history. That is correct, but such tasks only show in the global activity feed.
