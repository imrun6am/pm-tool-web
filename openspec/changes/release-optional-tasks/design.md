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

## Risks / Trade-offs

- **Losing the cascade.** Deleting a release no longer removes its tasks, so the board can collect unlinked leftovers. The board's "No release" filter makes them easy to find and archive.
- **Activity history.** Activity for an unlinked task has `release_id = null`, so it doesn't appear in any release's history. That is correct, but such tasks only show in the global activity feed.
