## Why

The tool can plan release windows and phases, but a release plan is more than dates: teams need to break work into tasks, discuss it, account for non-working days, and see an accountable history of who changed what. This change turns each release into a working hub — description, tasks/subtasks, comments, off-days — and adds an app-wide activity log so every change is attributable.

## What Changes

- Add an optional rich **description** to each release.
- Add **tasks** on a release: title, description, status (To Do / In Progress / In Review / Done), optional assignee, optional due date, and an optional link to one of the release's phases. Tasks support one level of **subtasks** that share the same shape.
- Add **comments** on both **releases and tasks** (a shared, polymorphic comment thread), each attributed to its author.
- Add **off-days** to a release: mark specific dates within the window as non-working (with an optional reason and a "mark weekends" helper). Off-days are shown on the timeline and used to compute working days; they are informational and never block scheduling.
- Add a **rich activity log**: every create/update/delete of a project, team, release, phase, off-day, task, or comment is recorded with the acting user, a description, and the changed values (old → new). A global **Activity** page and a per-release **history** panel surface it.
- **Collaboration permissions**: any authenticated user (admin or viewer) may add/edit tasks, subtasks, and comments. Editing releases/projects/teams and managing off-days remain admin-only. Comment edit/delete is limited to the comment's author or an admin.

## Capabilities

### New Capabilities
- `release-description`: Optional description text stored and shown on a release.
- `task-management`: Tasks and one level of subtasks on a release, with status, assignee, due date, and optional phase link; editable by any authenticated user.
- `commenting`: Polymorphic comment threads on releases and tasks, attributed to the author, with author/admin edit-delete.
- `release-off-days`: Mark/unmark non-working days within a release window (with reason + weekend helper); shown on the timeline and used for a working-day count.
- `activity-log`: An attributable, app-wide history of create/update/delete events with causer and old→new values, viewable globally and per release.

### Modified Capabilities
<!-- None. The optional release description is captured by the new `release-description`
     capability; existing release-planning window/phase/overlap requirements are unchanged.
     (The prior change is not yet archived, so there is no base spec to delta against.) -->


## Impact

- **Database**: add `description` to `releases`; new tables `tasks`, `comments` (polymorphic), `release_off_days`, `activities` (polymorphic subject + causer).
- **Models**: new `Task`, `Comment`, `ReleaseOffDay`, `Activity`; a `RecordsActivity` trait added to auditable models; relations added to `Release`, `User`.
- **Auth**: new `auth`-level (not admin-only) routes for tasks and comments; off-day routes remain admin-gated; a `CommentPolicy` for edit/delete.
- **Activity logging**: implemented in-app (no external package) via model events, for reliability on Laravel 13.
- **UI**: release show page gains description, tasks panel, off-days panel, comments thread, and a history panel; a new task detail page; a new global Activity page and nav link.
- **Seed data**: demo tasks/subtasks, comments, and a few off-days.
