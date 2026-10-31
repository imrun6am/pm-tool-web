## Why

Teams currently track daily work in an external Google Sheet (one tab per day, one row per member: morning task plan, day-end result, comment, work points, tickets). That data lives outside the tool, disconnected from teams and users, and offers no per-role visibility — the team lead's evaluation is either public or kept in yet another place. Bringing the tasksheet in-app gives each team a daily grid tied to real team membership, with a lead-only feedback column.

## What Changes

- New **Tasksheet** section (navbar entry): pick a team and a date, see a spreadsheet-like grid — one row per team member (developers and QA), columns matching the reference sheet: Task Plan at Morning, Day End Result, Comment, Work Points, Tickets, Ticket Count, Ticket Points.
- Day navigation (prev / next / today / date picker) like the daily notes page; team switcher for users on multiple teams.
- Developers and QA fill **their own row** for the day; a row is created on first save (upsert per member/team/date).
- **Feedback column for team leads**: leads (admin, CTO, team lead) see and edit a per-row Feedback field; developers, QA, and viewers never see it — enforced server-side, not just hidden in the UI.
- Leads can also edit any member's row (corrections), while members are limited to their own.
- A member who is absent can be **marked absent** for the day — casual leave or sick leave — by themselves or a lead; the row then shows a leave badge instead of task fields.
- **Any previous day's sheet is browsable** via the date navigation — the sheet is a permanent daily record.
- **Auto-absent for unfilled days**: a developer/QA row left empty for a past day automatically shows an "Absent" mark. The row can still be filled in or its leave type set later (the mark then clears), but a late-filled row permanently shows a hint that it was not added on the operating day.
- **Activity log**: tasksheet row saves are recorded in the app's activity feed (who saved whose row, which fields changed) — with the lead-only feedback excluded from the log so it cannot leak there.

## Capabilities

### New Capabilities
- `team-tasksheet`: Team-wise daily tasksheet grid — viewing by team and date, members filling their own row, upsert semantics, lead corrections, and the lead-only feedback column with server-side visibility enforcement.

### Modified Capabilities

<!-- None. No existing capability's requirements change; teams/roles are reused as-is. -->

## Impact

- **Database**: new `tasksheet_entries` table (`team_id`, `user_id`, `date`, plan/result/comment text fields, points and ticket fields, `feedback`, unique per member/team/date).
- **Backend**: `TasksheetEntry` model, `TasksheetController`, `TasksheetEntryRequest`, policy/gate for row editing and feedback visibility; `User` gains a lead-check helper.
- **Routes**: `tasksheet` index + entry upsert routes in `routes/web.php`.
- **UI**: `resources/views/tasksheet/` grid view; new navbar item.
- **No breaking changes**; existing teams/users/roles are reused, no changes to other features.
