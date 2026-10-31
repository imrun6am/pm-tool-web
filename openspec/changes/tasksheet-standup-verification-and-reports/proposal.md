## Why

Three gaps in the daily tasksheet, all of them about a lead's ability to run the day:

- **Standup attendance is not recorded anywhere.** Leads track who showed up to the daily standup out-of-band — in their head, or in a side document. It belongs next to the row it describes.
- **The daily sheet cannot be narrowed.** It lists every member of the team for that day, in one block. The question a lead actually asks — "who hasn't filled their sheet?", "who missed standup?" — takes a manual scan.
- **There is no way to get the sheet out of the tool.** Sharing a day, a span, or one person's record with someone who does not use the app means screenshots.

## What Changes

- **Standup verification** — a per-row checkbox recording whether a member attended that day's standup, toggled **directly from the list** without opening the row. Three states: attended, did not attend, and not yet marked (the default).
- **Leads only.** Setting attendance follows the same rule as the lead-only `feedback` column: a member's own save can never reach it, so it can be neither forged nor cleared from a member's client.
- Attendance is **not applicable on a full-day leave row** — someone on casual/sick leave was legitimately absent, and recording that as a standup no-show would misrepresent it.
- **Filters on both lists** — the daily team sheet and the per-user history page gain filters for standup attendance, fill status (complete / partial / empty), and leave status; the daily sheet additionally filters by member. These compose with the per-user page's existing team and date-range filters.
- **PDF reports** — download a tasksheet report for a **single day**, a **date range**, or **one member** over a range, rendered server-side via `barryvdh/laravel-dompdf`.
- A member may export **their own** record; team-wide exports are lead-only. The lead-only `feedback` column is **omitted from any PDF a non-lead can obtain**, including a member's export of their own record — it is the lead's private note *about* that member.

## Capabilities

### Modified Capabilities

- `team-tasksheet`: adds a lead-only standup attendance flag toggled inline from the sheet, filtering on the daily sheet and the per-user history, and PDF export of a day, a range, or a member.

<!-- Delta specs are written as ADDED requirements, matching the repo convention: main specs under openspec/specs/ have not been synced yet, so there is no synced requirement text for a MODIFIED block to amend. -->

## Impact

- **Database**: new nullable `standup_attended` boolean on `tasksheet_entries` — nullable so "not yet marked" stays distinct from "did not attend", which the filter depends on.
- **Dependency**: adds `barryvdh/laravel-dompdf` (composer only; no Node, no system Chrome, so CI and MAMP behave the same).
- **Backend**: `TasksheetEntry` gains attendance state helpers; `TasksheetService` gains a lead-only `setStandupAttendance()`, filter application for both lists, and a report query; a new `TasksheetReportController` (or report action) rendering a print Blade view to PDF; `TasksheetEntryPolicy` gains a `verifyStandup` ability.
- **Routes**: a dedicated inline toggle endpoint, and a report download route — both web and API.
- **UI**: attendance checkbox in the sheet row; filter bars on the daily sheet and the per-user page; an Export PDF action carrying the active filters.
- **Docs**: `docs/api-v1.md` — the tasksheet rows gain the toggle and report endpoints and the attendance field.
- **No breaking changes**: existing rows read as "not yet marked", every filter is optional, and no existing column changes meaning.

## Notes and Non-Goals

- **Role scope**: the request named "team lead / admin / tech lead". This uses the existing `User::isLead()` tier, which is those three **plus CTO** — the same predicate that already gates `feedback` and lead corrections. Introducing a second, narrower leadership predicate that excludes only the CTO would leave two competing definitions of "lead" in one feature. Say so if the CTO should genuinely be excluded.
- Not in scope: standup attendance history/analytics or a per-member attendance rate; notifying absentees; scheduled or emailed reports; exporting to CSV/Excel; and any change to who may fill a row.
