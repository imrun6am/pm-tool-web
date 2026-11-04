## Why

Log tables and files only ever grow, and nothing clears them. Keeping everything for good isn't needed: people look at the last few weeks.

## What Changes

- A daily scheduled cleanup removes logs older than one configurable retention period, 3 months by default.
- It covers the activity log, AI usage logs, failed queue jobs and log files.
- Activity rows that belong to a release stay, so a release's history is never cut short.
- Older activity rows get the release link they were missing, so they're kept and appear in release History.
- The 90-day activity view stops comparing with the previous 90 days, because that period is no longer kept.
- Rating evidence reads completed tasks from the status history, so it still works for older periods.

## Capabilities

### New Capabilities
- `log-retention`: what counts as a log, how long it's kept and how it's removed.

### Modified Capabilities
<!-- None: `openspec/specs/` has no base specs yet. -->

## Impact

- **Data**: a `created_at` index on `activities`, and a backfill of `activities.release_id`. The first run removes rows for good.
- **Backend**: `Activity`, `AiUsageLog`, new `LogRetention`, `routes/console.php`, `ActivityInsights`, `ScoreEvidence`.
- **Frontend**: the 90-day activity view drops its comparison pills, and the deletions card says "on record" rather than "all time". Release History and the team, project and release figures on the Activity page now include the older rows.
- **API**: `GET /activities/analysis` returns `previous` and `change` as null where there's no comparison.
- **Operations**: servers need `php artisan schedule:run` in cron, and `LOG_STACK=daily` for log files to be cleaned.
