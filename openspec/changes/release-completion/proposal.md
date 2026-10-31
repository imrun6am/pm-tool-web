## Why

Once a release ships, it should drop off the planning dashboard so the timeline only shows work still in flight — but the record must not disappear. Teams also want to capture a closing summary (what shipped, follow-ups) when they finish. This adds a completion state with rich notes, hides completed releases from the dashboard and overlap checks, and gives a dedicated all-releases list.

## What Changes

- Let an admin **mark a release complete** and **reopen** it. Completing records who completed it, when, and optional **completion notes** (Markdown-rendered rich text).
- **Completed releases are hidden from the dashboard** timeline and are **excluded from team overlap detection** (a finished release no longer books the team).
- Add an **all-releases list** page (a table of every release with status, project, team, quarter, window) filterable by status (active/completed), project, team, and year — reachable from a new **Releases** nav link.
- Show completion state on the release page: a **Completed** badge, who/when, and the rendered notes; the dashboard shows only ongoing releases.

## Capabilities

### New Capabilities
- `release-completion`: Mark a release complete (with rich completion notes) or reopen it; completed releases are hidden from the dashboard and overlap checks and remain visible in an all-releases list.

### Modified Capabilities
<!-- None as a formal delta; the prior release-planning/planning-dashboard specs are not archived
     to openspec/specs, so the dashboard-hiding behavior is expressed in this new capability. -->

## Impact

- **Database**: add `completed_at`, `completed_by`, `completion_notes` to `releases`.
- **Model**: `Release` gains completion fields, `isComplete()`, `ongoing`/`completed` scopes, `completedBy` relation, and a rendered-notes accessor (Markdown → safe HTML).
- **Controllers/routes**: `ReleaseController@index` (all-releases list, auth), `@complete` / `@reopen` (admin); `DashboardController` and `OverlapChecker` exclude completed releases.
- **UI**: releases index page + nav link; completion panel + badge on the release page.
- **Seed data**: mark one demo release complete with notes.
