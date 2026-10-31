## 1. Access Foundation

- [x] 1.1 Add `User::hasLimitedAccess()` (developer or qa)
- [x] 1.2 Create `EnsureFullAccess` middleware (403 for limited users) and register alias `full-access` in `bootstrap/app.php`
- [x] 1.3 Wrap restricted routes in a `full-access` group in `routes/web.php`: releases index/show/document download, projects index/show, teams index/show, activity feed, `releases/{release}/comments` and `releases/{release}/tasks` writes — with a comment that planning routes belong here

## 2. Navigation & Link Degradation

- [x] 2.1 Filter the `$nav` array for limited users to Dashboard, Board, Calendar, Notes, Meetings, Tasksheet
- [x] 2.2 Render release references as plain text for limited users: board header + board card, task show (header + back link), meeting-notes index badge + show page, event show page

## 3. Member Dashboard

- [x] 3.1 Branch `DashboardController`: limited users get `dashboard-member` with its own data; other roles unchanged
- [x] 3.2 Member dashboard queries: open assigned tasks (due-date asc nulls-last, overdue flag), today's tasksheet entry per team, upcoming meetings (14 days, attendee or creator, capped, soonest first)
- [x] 3.3 Create `dashboard-member.blade.php`: My tasks (status badge, due date, overdue flag, links), Today's tasksheet (per-team status + "Fill now" link), Upcoming meetings (title, when, link), empty states

## 4. Tests & Verification

- [x] 4.1 Feature tests: dev and qa get 403 on releases index/show, projects, teams, activity, release comment/task writes; allowed pages (board, calendar, notes, meetings, tasksheet, task show) still load
- [x] 4.2 Feature tests: viewer and lead unchanged (releases index OK, planning dashboard renders)
- [x] 4.3 Feature tests: member dashboard — dev sees assigned open task (done task absent), unfilled tasksheet prompt per team, upcoming attended meeting (unrelated meeting absent); admin still sees timeline dashboard
- [x] 4.4 Feature tests: nav — restricted labels absent for dev on an allowed page, present for viewer; release name on board/task page is plain text for dev
- [x] 4.5 Run full test suite and fix regressions

## 5. Revision: partial-fill status, all statuses clickable

- [x] 5.1 `TasksheetEntry`: `TASK_FIELDS` const, `filledFieldCount()`, `isFullyFilled()`, `isPartiallyFilled()` helpers
- [x] 5.2 Member dashboard: four linked statuses per team — Not filled / Partially filled (with n/7 count) / Filled ✓ / leave label — all linking to that team's sheet for today
- [x] 5.3 Feature tests: partial row shows "Partially filled", full row shows "Filled ✓", statuses link to the sheet

## 6. Revision: developers/QA get a restricted release detail view

- [x] 6.1 Routes: move `releases.show`, document download, `releases.tasks.store`, `releases.comments.store` out of `full-access` to the general authenticated section; move document upload out of `manage-releases` (releases *list*, projects, teams, activity stay `full-access`)
- [x] 6.2 `ReleaseDocumentController@store`: block pure viewers (leads + dev/QA may upload)
- [x] 6.3 `releases/show`: hide timeline band, off-days, history, edit/complete controls for limited users; upload form gated to non-viewers; members stay view-only (edit already lead-gated)
- [x] 6.4 Revert release-name link degradation — names link to the detail page for all roles (board card/header, task page, meeting note, event)
- [x] 6.5 Feature tests: dev/QA can view a release + create task/comment/link/upload, but 403 on releases index/projects/teams/activity and cannot edit; viewer cannot upload; release names render as links
