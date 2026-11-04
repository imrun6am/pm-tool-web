## Context

Tasks have no started or completed columns. `TaskCompletions` and `MemberAnalyticsService` each decode `activities.properties` to find status moves.

## Goals / Non-Goals

**Goals:**
- Delivery figures that don't depend on the activity log being kept.
- A base for flow reports and release forecasts.

**Non-Goals:**
- New reports or API endpoints; those come with the flow reports.
- Pruning the activity log.

## Decisions

**Written from the model.** `Task`'s `created` and `updated` events write the row, so the board, task page, API and AI paths are all covered without touching them. Query-builder updates would skip it, and none change a task's status today.

**History goes with its task.** `task_id` cascades on delete, because completion figures only ever read tasks that still exist. The activity log keeps the audit record of a deleted task.

**`ActivityInsights::flow` stays on the log.** It filters by who acted and by release, including deleted tasks, which is what an audit report should do.

**Backfill copies only what was logged.** A task with no logged status keeps no history and stays flagged `estimated`, as before. Rows for deleted tasks are skipped, and a user who no longer exists becomes `changed_by = null`.

## Risks / Trade-offs

- [A status change between running the migration and deploying the code isn't recorded] → Run the migration right before the code goes live. The task's figures fall back to `estimated`.
