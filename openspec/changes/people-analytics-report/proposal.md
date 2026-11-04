## Why

The numbers an admin needs about a person or a team are spread over the dashboard, Performance, Reports, the tasksheet and KPI reviews, each with its own window and scope. To answer "how is this team doing, and who needs help?", an admin has to visit five pages and add things up. No single report can be shared with management.

## What Changes

- A new admin-only People analytics report: one row per team, one row per person, for a date range and an optional team.
- A page per person with their delivery, workload, time, tasksheet, requests, meetings, ratings and KPI results, plus the tasks behind the numbers.
- CSV and PDF exports of the same figures, and a matching API.
- A number with too little data behind it says so instead of showing a misleading rate.
- There is no department field in the app, so teams stand in for departments.
- No weighted score. The weighted scorecard the user set aside stays out, but the figures are grouped so it can be added later.

## Capabilities

### New Capabilities
- `people-analytics-report`: admin analytics per team and per person, with exports.

### Modified Capabilities
<!-- None: `openspec/specs/` has no base specs yet. -->

## Impact

- **Backend**: new `PeopleAnalyticsService`, `PeopleReportColumns`, `PeopleReportCsv`, `ReadsPeopleReportFilters` and `PeopleReportController`. `TaskCompletions` also returns each task's team and estimate. `PerformanceAnalytics` gets one public method for a score over any period. `Metric` gets `measure()`.
- **Frontend**: new `reports/people/*` views and `resources/js/people-table.js`. A Reports tab and a sidebar item for admins.
- **API**: new `GET /reports/people`, `/reports/people/export` and `/reports/people/{user}`, admin only.
- **Data**: none. No migration.
