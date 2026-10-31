## Why

After the dashboard redesign, several pages still look like the older system:

- **Profile:** a narrow, generic stack of forms. The user asked for a full-width, professional settings page.
- **Task and event detail:** they read like raw edit forms, with "Back to …" links at the bottom and uppercase field labels.
- **Other dashboard-style pages** (performance, tasksheet, team and project pages): their summary rows still use large separate stat cards rather than the compact KPI strip the main dashboard now uses.
- **Calendar, release detail and performance-member pages:** headers and grids that don't follow the shared page-header pattern.

## What Changes

- **Profile becomes a full-width settings page:**
  - a sticky section nav (Profile, Password, Danger zone) that highlights as you scroll, shown as tabs on mobile;
  - an account overview card (large avatar, name, email, role, teams, member since);
  - full-width sectioned cards for profile info and password, and a clearly separated danger zone.

  Routes, error bags and the delete-account modal behave as before.
- **A shared KPI strip:** a new `x-stat` cell component inside `.stat-strip` replaces the separate stat tiles on performance, performance member, tasksheet, team and project pages.
- **Task detail:** a two-column layout. The main column holds the title and description, subtasks and comments; a properties panel holds status, assignee, due date, phase, created by and the save action. Breadcrumbs go back to the release.
- **Event detail:** a document layout like meeting notes. Breadcrumbs go back to Calendar, with a details side panel, attendees with avatars, and linked meeting notes.
- **Release detail:** breadcrumbs, a status badge in the header, and calmer sentence-case labels in the side panels.
- **Calendar:** a standard page header with month navigation in its actions, a highlighted today, calmer weekday headings, and the "tip" line moved into the description.
- **Performance member:** breadcrumbs and the shared header, with tighter competency rows.
- **Unchanged:** no route, controller, validation or permission changes, and existing tests pass untouched.

## Capabilities

### New Capabilities
- `settings-and-detail-pages`: the settings page layout and section nav; the KPI strip on dashboard-style pages; and the layout rules for task, event, release, calendar and performance-member detail pages.

### Modified Capabilities

<!-- None (no archived specs). Extends ui-components / page-layouts / dashboard. -->

## Impact

- **Views:**
  - `profile/*`
  - `tasks/show`, `events/show`, `releases/show` (header and side panels), `calendar/index`
  - `performance/{index,show}`, `tasksheet/index`, `teams/show`, `projects/show`
- **Components:** new `components/stat.blade.php`, plus an `xl` avatar size.
- **Tests:** new feature tests for the settings sections, the task and event detail breadcrumbs, and the KPI strip. The existing suite passes unchanged.
