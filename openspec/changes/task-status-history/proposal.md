## Why

Cycle time, rework and completion figures are worked out by reading JSON out of the activity log. That ties delivery reports to the audit log: pruning old activity, which is already a deferred idea, would quietly damage those numbers. Flow reports and forecasts planned next need a dependable record of every status a task passed through.

## What Changes

- A new `task_status_changes` table holds one row per status a task moved into: from, to, who and when.
- `Task` writes these rows itself on every create and status change, so every code path is covered.
- The migration brings over existing history from the activity log, for tasks and users that still exist.
- `TaskCompletions` and `MemberAnalyticsService` read the new table. Their results don't change.
- `ActivityInsights::flow` stays on the activity log, because it is an audit report that also covers deleted tasks and filters by who acted.

## Capabilities

### New Capabilities
- `task-status-history`: a dedicated record of task status changes that delivery reports read from.

### Modified Capabilities
<!-- None: `openspec/specs/` has no base specs yet, and reported figures don't change. -->

## Impact

- **Data**: one new table, filled from `activities`. The activity log isn't changed.
- **Backend**: `Task`, new `TaskStatusChange`, `TaskCompletions`, `MemberAnalyticsService`.
- **API and UI**: no change.
- **Tests**: new `TaskStatusHistoryTest`.
