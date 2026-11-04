## Why

Hours are logged on tasks now, but they can only be seen one task at a time. Leads can't answer where the hours went, or whether estimates can be trusted when planning the next release.

## What Changes

- A new Time tab under Reports, for leads: hours by release, team and person, for a period, team or release.
- Estimate accuracy: logged against estimated hours on tasks finished in the period, and the tasks furthest from their estimate.
- A CSV export of every entry.
- A matching API.
- These figures never feed performance scores or KPI reports, and the page says so.

## Capabilities

### New Capabilities
- `time-reports`: logged hours and estimate accuracy.

### Modified Capabilities
<!-- None: `openspec/specs/` has no base specs yet. -->

## Impact

- **Backend**: new `TimeReportService`, `TimeReportCsv`, `ReadsTimeReportFilters`.
- **Frontend**: new `reports/time`.
- **API**: new `GET /reports/time` and `GET /reports/time/export`.
- **Data**: none.
