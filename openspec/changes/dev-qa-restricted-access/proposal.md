## Why

Developers and QA currently see the whole planning tool — release timelines, projects, teams, the org-wide activity feed — although their daily work is the board, calendar, notes, meetings, and the tasksheet. Their landing page is a release-planning timeline that means little to them. Restricting their access focuses the tool per role and keeps planning surfaces (and their data) to leads, viewers, and admins.

## What Changes

- **Developers and QA can access only**: Board, Calendar (events), Notes, Meetings (meeting notes), Tasksheet, their Dashboard, and their own Profile. Everything else — Releases, Projects, Teams, Activity — returns 403, enforced by middleware (not just hidden links). Task detail/edit pages stay accessible (they are the board's workflow); release-scoped writes (release comments, release task creation) are blocked with the release pages.
- **Navigation** for developers/QA shows only the allowed sections.
- **Links into restricted areas** (release names on board cards, task pages, meeting notes, events) render as plain text for developers/QA instead of links.
- **A member dashboard replaces the planning timeline** for developers/QA: their assigned open board tasks (with status and due dates, upcoming ones first), today's tasksheet status per team (filled / on leave / not filled, with a fill shortcut), and their upcoming meetings.
- Admins, CTOs, team leads, and viewers keep the current dashboard and full read access — no behavior change for them.

## Capabilities

### New Capabilities
- `role-restricted-access`: Which sections developer/QA roles may reach, middleware enforcement, navigation and link degradation for restricted areas.
- `member-dashboard`: The personal dashboard for developers/QA — assigned tasks, today's tasksheet status, upcoming meetings.

### Modified Capabilities

<!-- None as main specs: earlier capabilities (release-planning, kanban, calendar, etc.) were specified before any role restrictions and remain unchanged for non-restricted roles; the restriction layer is specified in role-restricted-access. -->

## Impact

- **Backend**: `User::hasLimitedAccess()` helper; new `EnsureFullAccess` middleware + alias; route regrouping in `routes/web.php`; `DashboardController` branches to a member dashboard with its own queries.
- **UI**: `dashboard-member.blade.php`; nav filtering in `layouts/navigation.blade.php`; conditional release links in board/task/meeting-note/event views.
- **No schema changes.** No behavior change for admin/cto/team_lead/viewer.
