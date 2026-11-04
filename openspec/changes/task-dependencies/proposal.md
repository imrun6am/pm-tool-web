## Why

Stuck work is invisible today. A task waiting for sandbox keys or for another task looks like any other in progress, so nobody steps in, and the person waiting has to keep checking whether they can start.

## What Changes

- Anyone who can edit a task can flag it as blocked with a reason, and clear the flag.
- A task can wait for other tasks, by key. Loops and waiting for itself are refused.
- A task is blocked while flagged or while it waits for an unfinished task. Finishing it clears the flag.
- Cards and the task page show a Blocked badge, and lists can filter on it.
- The team lead and assignee are told when a task is flagged. The assignee is told when the last blocker finishes.

## Capabilities

### New Capabilities
- `task-dependencies`: the blocked flag, dependencies between tasks, their display, filter and notifications.

### Modified Capabilities
<!-- None: `openspec/specs/` has no base specs yet. -->

## Impact

- **Data**: `tasks.blocked_at`, `tasks.blocked_reason`, new `task_dependencies`.
- **Backend**: `Task`, `TaskDependencyService`, two requests, web and API controllers, `NotificationService`, two notifications, `TaskEngagementObserver`.
- **Frontend**: new `tasks/_blocking` panel, board card badge, inbox kinds.
- **API**: new endpoints and task fields, documented in `docs/api-v1.md`.
