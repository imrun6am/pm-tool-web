## 1. Status history

- [x] 1.1 Add the `task_status_changes` migration with the activity log backfill
- [x] 1.2 Add `TaskStatusChange` and write rows from `Task`'s events
- [x] 1.3 Read history in `TaskCompletions` and `MemberAnalyticsService`
- [x] 1.4 Add `TaskStatusHistoryTest`, including pruning and backfill cases
- [x] 1.5 Apply the migration by `--path` on the dev database
