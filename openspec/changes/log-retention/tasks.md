## 1. Retention

- [x] 1.1 Add `LOG_RETENTION_MONTHS` and `LogRetention`
- [x] 1.2 Make `Activity` and `AiUsageLog` `MassPrunable`, keeping release rows
- [x] 1.3 Add the `created_at` index on `activities`
- [x] 1.4 Schedule `model:prune` for both models, and `queue:prune-failed`
- [x] 1.5 Keep log files for the same period on the `daily` channel
- [x] 1.6 Backfill `release_id` on older activity rows

## 2. Readers

- [x] 2.1 Hide activity comparisons whose baseline isn't kept
- [x] 2.2 Relabel the "all time" total
- [x] 2.3 Read rating evidence from the status history

## 3. Checks

- [x] 3.1 Add `LogRetentionTest`
- [x] 3.2 Update the docs
- [x] 3.3 Try the prune on a scratch SQLite database
