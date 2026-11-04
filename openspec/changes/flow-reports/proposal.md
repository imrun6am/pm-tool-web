## Why

Nothing shows how work moves over time: whether reviews are piling up, whether more comes in than gets finished, or how long a task usually takes. Leads find out when a date slips.

## What Changes

- A new Reports page, for leads and viewers, covering the last 12 weeks, for all teams or one.
- A cumulative flow chart (tasks in each status per week), tasks added against tasks finished, and days to finish each task with the 85% line.
- A list of work in progress for longer than 85% of tasks take.
- A matching API endpoint.
- No people-level figures, so it can't become a leaderboard.

## Capabilities

### New Capabilities
- `flow-reports`: how work moves through a team over time.

### Modified Capabilities
<!-- None: `openspec/specs/` has no base specs yet. -->

## Impact

- **Backend**: new `FlowReportService`, `ReportController` for web and API.
- **Frontend**: new `reports/flow`, `reports/_tabs`, `partials/stacked-area-chart`, `partials/scatter-chart`, a sidebar item and one icon.
- **API**: new `GET /reports/flow`.
- **Data**: none.
