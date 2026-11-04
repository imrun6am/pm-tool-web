## Context

The activity log was left unpruned on purpose (`release-collaboration-and-audit`). `task-status-history` then moved delivery figures off it, which made pruning possible. Readers of `activities` were checked before choosing what to delete:

| Reader | Looks back | With 3 months kept |
|---|---|---|
| Activity page, 7 and 30 days, flow lookback | Up to 90 days | Unchanged |
| Activity page, 90 days | 90 days | Unchanged |
| Activity page, "vs previous 90 days" | 180 days | No baseline, so hidden |
| Activity page, "all time" total | Everything | Relabelled "on record" |
| Release, task, team and project history | Latest rows | Older rows go, release rows stay |
| Standup digest | Previous weekday | Unchanged |
| Rating evidence | Any past period | Moved to status history |
| Tasksheet AI draft | Any past day | Finds no moves for days past the limit |
| `task_status_changes` backfill | One-off migration | Already run |

The AI usage page looks back 90 days at most.

## Goals / Non-Goals

**Goals:**
- One setting decides how long logs are kept.
- Cleaning up doesn't hold locks that get in people's way.
- No chart shows a misleading number because its data was removed.

**Non-Goals:**
- Task time logs and status history. They're work records, not logs.
- `job_batches`, `notifications` and `sessions`. They aren't logs.
- Archiving rows elsewhere before removing them.

## Decisions

**`LOG_RETENTION_MONTHS`, read through `LogRetention`.** Everything takes its cutoff from one place. Values under 1 count as 1, so a typo can't delete a whole table.

**The cutoff is the start of the day, N calendar months ago, without overflow.** Three months is never less than 89 days back, which is where the 90-day window starts, so the window stays whole.

**`MassPrunable`, added to the existing `model:prune` entry.** These rows have no files or child rows to clean up, so there's no need to load each one. It deletes 1,000 rows at a time, each in its own statement.

**A `created_at` index on `activities`.** Without one, every batch scans the table and InnoDB locks each row it reads, which would block people writing to the log.

**Release rows are kept.** A row stays if it has a `release_id`, or if its subject is a `Release`, `ReleasePhase` or `ReleaseOffDay`. The second test covers phases that have since been removed, which the backfill can't link.

**Old rows are linked by a one-off migration.** Rows written before 2026-11-01 have no `release_id`, even when they describe a live release, so release History skips them and the prune wouldn't know to keep them. The migration gives each row the release its subject's `activityReleaseId()` gives today. It only fills empty values and writes in batches of 500. It must run before the first prune, which `migrate` before `schedule:run` guarantees.

**Failed jobs use `queue:prune-failed`,** which already deletes in batches. It takes hours, so `LogRetention` converts the cutoff.

**Log files use the `daily` channel.** Monolog removes old files itself when it starts a new day and never touches the file in use. Files are kept for the retention period at 30 days a month. A command deleting files would only repeat this.

**Comparisons depend on the data being kept.** `ActivityInsights` compares only when the previous window starts after the cutoff, so a longer retention brings the comparison back without a code change.

## Risks / Trade-offs

- [Deleted rows can't be recovered] → Limited to tables nothing else depends on, and the first run's size was checked in advance.
- [Old task history disappears from task pages] → Accepted by the user. Status moves live on in `task_status_changes`.
- [Tasksheet AI drafts for days past the limit see no board moves] → Rare, and the draft is reviewed before saving.
- [Release rows are kept forever] → They're a small share of the log.
- [A task moved between releases links all its old rows to the current one] → The log doesn't reliably record the release at the time, and this matches what new rows would get.
- [Rows for tasks and notes that no longer exist can't be linked] → They go with the prune like other unlinked rows.
