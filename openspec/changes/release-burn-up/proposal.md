## Why

We work by release, not by sprint, and the release page only says how many tasks are done today. It can't show whether scope has been growing or whether finishing has slowed down, which is what a lead needs to know while there's still time to act.

## What Changes

- The release page gets a Progress card with a burn-up chart: total tasks and finished tasks from the release's start to today.
- Tasks added later show as the scope line rising; reopened work drops out of Done from the day it reopened.
- The same figures are in a table under the chart and on a new API endpoint.

## Capabilities

### New Capabilities
- `release-burn-up`: scope against finished work over a release.

### Modified Capabilities
<!-- None: `openspec/specs/` has no base specs yet. -->

## Impact

- **Backend**: new `ReleaseProgressService::burnUp`, `ReleaseController::show` passes it to the page.
- **Frontend**: new `releases/_progress`, new reusable `partials/line-chart`.
- **API**: new `GET /releases/{id}/progress`, open to anyone who can see the release.
- **Data**: none. It reads the status history added in Phase 1.
