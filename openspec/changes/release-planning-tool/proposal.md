## Why

Release work for multiple projects is planned across quarters, and each team owns several releases over the year. Today there is no single view that shows every release timeline side by side, so it is easy to accidentally double-book a team — scheduling a new release for a team that is already busy on another release in that window. This tool gives a single planning dashboard that visualizes every release timeline, breaks each release into its Dev/QA/Retest/Release phases, and warns when a team's releases overlap so schedules can be corrected before work starts.

## What Changes

- Introduce authenticated access with **Admin** (full CRUD) and **Viewer** (read-only) roles.
- Manage **Projects** (the products that get released) and **Teams** (the groups that execute releases).
- Manage **Release Plans**: each belongs to a project, is owned by exactly one team, is placed in a quarter of a year, and has an overall start/end window.
- Break each release into four ordered **phases** — Development, QA, Retest, Release — each with its own start/end dates inside the release window.
- Detect and **warn on team booking overlaps**: when a release's window overlaps another release owned by the same team, surface a clear warning on save and highlight the conflict on the dashboard. Overlaps are warned, never blocked.
- Provide a **planning dashboard**: a timeline (Gantt-style) of all release plans showing start/end, phase segments, filterable by quarter/year/project/team, with overlapping team bookings visually flagged.
- Attach and manage **documents** on each release (upload, list, download, delete).

## Capabilities

### New Capabilities
- `auth-and-roles`: Authenticated login with Admin and Viewer roles governing who can create/edit versus who can only view.
- `project-management`: Create, view, edit, and archive projects that releases belong to.
- `team-management`: Create, view, edit, and archive teams that own releases.
- `release-planning`: Create and manage release plans (project, owning team, quarter/year, overall window) and their ordered Dev/QA/Retest/Release phase dates, including team overlap warnings on save.
- `release-documents`: Upload, list, download, and delete documents attached to a release.
- `planning-dashboard`: Timeline visualization of all releases and phases with filtering and visual flagging of team booking overlaps.

### Modified Capabilities
<!-- None — this is a greenfield application. -->

## Impact

- **New Laravel application** scaffolded in this directory (Laravel 12, Blade, MySQL).
- **Auth**: Laravel Breeze (Blade stack) for login/registration scaffolding, extended with a `role` field and policy/gate enforcement.
- **Database**: new tables — `users` (with role), `projects`, `teams`, `releases`, `release_phases`, `release_documents`.
- **Storage**: local `storage/app` disk (via `storage:link`) for release document uploads.
- **Frontend**: Blade views + Tailwind (shipped by Breeze) for the dashboard timeline and CRUD screens; a lightweight CSS/JS timeline (no heavy external Gantt dependency).
- **Seed data**: demo projects, teams, and releases to exercise the dashboard and overlap warnings.
