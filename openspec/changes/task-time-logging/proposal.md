## Why

Teams want hours recorded against tasks. Without them there is no way to compare estimates with actual time, see where a release's effort went, or plan capacity from real numbers. The tasksheet records daily plans and points, but not time per task.

## What Changes

- People log time on a task from its page: time spent, date and an optional note.
- A task's total includes its subtasks.
- People change their own entries for 7 days. Org-level leads and the team's own lead can correct any entry at any time.
- Viewers see totals only, and can't log time.
- New web and API endpoints, with every entry written to the activity log.

## Capabilities

### New Capabilities
- `task-time-logging`: logging, listing and correcting hours on tasks, with totals that include subtasks.

### Modified Capabilities
<!-- None: `openspec/specs/` has no base specs yet. -->

## Impact

- **Data**: new `task_time_logs` table.
- **Backend**: `TaskTimeLog`, `TaskTimeLogPolicy`, `TaskTimeService`, `TaskTimeLogRequest`, web and API controllers, `App\Support\Duration`.
- **Frontend**: new `tasks/_time-log` panel on the task page.
- **API**: new endpoints, documented in `docs/api-v1.md`.
