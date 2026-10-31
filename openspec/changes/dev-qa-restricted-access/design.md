## Context

Access control today is additive: `admin`, `manage-releases`, `manage-users` middleware guard *write* surfaces, while read surfaces (releases, projects, teams, activity) are open to every authenticated user. This change introduces the first *subtractive* rule: developer/QA roles lose read access to planning surfaces. The navbar is a single `$nav` array; the dashboard is one controller rendering a release timeline; several allowed views link into now-restricted release pages.

## Goals / Non-Goals

**Goals:**
- Hard server-side enforcement (403) for restricted sections, with UI (nav, links) degrading consistently.
- A member dashboard that answers "what am I doing today": assigned tasks, tasksheet status, upcoming meetings.
- Zero behavior change for admin, CTO, team lead, viewer.

**Non-Goals:**
- No per-user or per-team access grants — purely role-based.
- No new role; developer and QA are treated identically.
- No filtering of *content within* allowed pages (e.g. the board still shows all releases' tasks, meeting notes still show release names as text).
- No API/token surface — this is a session-based Blade app.

## Decisions

1. **`User::hasLimitedAccess()`** (role ∈ {developer, qa}) rather than enumerating "full access" roles — new roles default to full access, matching the app's open-by-default philosophy; the limited set is the explicit exception.

2. **One `EnsureFullAccess` middleware (alias `full-access`)** aborting 403 for limited users, wrapped around the restricted read routes: releases (index/show/document download), projects, teams, activity — plus the release-scoped collaboration writes (`releases/{release}/comments`, `releases/{release}/tasks`) since their referring pages are restricted. Rejected: per-route `can:` gates (scattered) and blade-only hiding (not enforcement). Existing role middleware already covers admin/manage surfaces.

3. **Task-level routes stay open** (`tasks.show/update/status/destroy`, subtasks, task comments): they are the board's workflow and the member dashboard links to them. Board task creation (`board.tasks.store`) remains the limited user's way to add work items.

4. **Navigation filters by the same helper** — limited users see Dashboard, Board, Calendar, Notes, Meetings, Tasksheet. The nav array gains nothing new; entries are skipped for limited users.

5. **Release links degrade to plain text** for limited users in the allowed views that carry them (board header/cards, task show, meeting-note index/show, event show). The release *name* stays visible (context is useful); only the hyperlink goes. Implemented per-view with the helper — a shared Blade component would be over-abstraction for ~6 sites.

6. **Member dashboard = same route, branched controller** — `DashboardController` returns `dashboard-member` for limited users so "/dashboard" stays the single home. Sections:
   - **My tasks**: open tasks (`status != done`) assigned to the user, subtasks included, due-date ascending with nulls last, then id; overdue and due-soon flagged; links to `tasks.show`.
   - **Today's tasksheet**: one line per team the user belongs to — filled / leave label / "not filled yet" with a "Fill now" link to the team+today sheet.
   - **Upcoming meetings**: meeting-type events in the next 14 days where the user is an attendee or the creator, soonest first, capped; links to the event.
   Rejected: reusing the timeline view with filters — the timeline answers planning questions, not "what am I doing today".

7. **Viewer keeps the planning dashboard** — viewers exist to observe planning, so only limited users get the member dashboard.

## Risks / Trade-offs

- [Restricted data reachable through non-obvious routes] → Tests probe each restricted named route directly as dev and qa; the middleware wraps whole route groups, not individual URIs.
- [Board/meeting pages still expose release *names*] → Accepted: names are context, pages/details are what's restricted (per "no content filtering" non-goal).
- [Future routes added to open sections silently reachable] → The route file now has an explicit `full-access` group with a comment; new planning routes belong inside it.
- [Member dashboard queries on every load] → Small bounded queries (assigned open tasks, ≤ teams entries, capped events); no caching needed at this scale.
