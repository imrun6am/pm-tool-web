## Context

The reference is a Google Sheet per team/month: one tab per working day, one row per member, columns Task Plan at Morning / Day End Result / Comment / Work Points / Tickets / Ticket Count / Ticket Points. The app already has Teams with member pivots (`team_user`), string roles on users (admin, cto, team_lead, developer, qa, viewer) with helper methods, a daily-notes page with day navigation to mirror, and an established server-rendered Blade + Alpine pattern (inline edit forms, form POSTs, flash messages).

The one sensitive requirement: the team lead's **Feedback** per row must be invisible to developers and QA — so it must never be rendered for them (server-side gate), not merely hidden with CSS, and the update path must reject feedback writes from non-leads.

## Goals / Non-Goals

**Goals:**
- One grid per team per date, rows auto-derived from live team membership (developers + QA).
- Members self-report; leads correct anyone and leave private feedback.
- Upsert semantics — no pre-seeding of empty rows; a row exists once someone saves it.
- Reuse existing conventions: role helpers, policy authorization, day navigation, inline Alpine editing.

**Non-Goals:**
- No month overview / multi-day matrix in v1 (single day per view, like the sheet's tabs).
- No aggregation reports (weekly/monthly point totals) — future change.
<!-- (revised) rich text in cells was originally excluded; superseded by decision 6d -->

- No off-day awareness — any date can hold entries.
- No notifications or reminders to fill the sheet.

## Decisions

1. **Single `tasksheet_entries` table, upsert per (team, user, date)** — columns: `team_id` (FK cascade), `user_id` (FK cascade), `date`, `plan`, `result`, `comment`, `tickets` (all nullable text), `work_points`, `ticket_count`, `ticket_points` (nullable unsigned integers), `leave_type` (nullable string: `casual` | `sick`), `feedback` (nullable text), timestamps, unique index on (`team_id`, `user_id`, `date`). Alternative — an `entries` row per cell (EAV) — rejected: the column set is fixed and typed. `team_id` is stored (not derived via the user) because membership changes over time; the entry belongs to the team sheet it was written on.

2. **Rows = active team members with role developer or qa** — matches "tasksheet for developers and qa". Leads appear as editors, not rows. A member who leaves the team keeps their historical entries (visible on past dates via stored `team_id`), but stops getting a row on new dates; rows for a date are the union of current dev/qa members plus anyone with a saved entry on that date.

3. **Lead check = new `User::isLead()`** returning true for admin, cto, team_lead. Existing helpers don't fit (`canManageReleases` excludes CTO, `canManageUsers` excludes team leads). Used for: seeing/editing the Feedback column and editing others' rows.

4. **Authorization in `TasksheetEntryPolicy`** — `update(User, TasksheetEntry)`: own row or `isLead()`. Feedback write-protection lives in the request/controller: `TasksheetEntryRequest` drops/rejects a `feedback` input from non-leads, and the Blade view only renders the Feedback column inside an `@if($user->isLead())` — never in the DOM for developers/QA.

5. **Routes**: `GET tasksheet` (index with `?team=` and `?date=` params, defaults: the user's first team, today) and `PUT tasksheet/entries` (upsert keyed by team+user+date from validated input; `updateOrCreate` on the unique triple). Registered in the collaboration section — the page is viewable by any authenticated user (consistent with the app's open-read philosophy); editing is what's restricted. Named `tasksheet.index` / `tasksheet.entries.upsert`.

6. **Grid UI, one Alpine inline form per row** — table layout mirroring the sheet's column order; each row shows saved values as text with an Edit action when permitted (own row, or lead). Edit expands the row into a form (textareas for plan/result/comment/tickets, number inputs for points/counts, feedback textarea for leads only) that PUTs the whole row. Mirrors the daily-notes inline-edit pattern rather than cell-level AJAX — one save per row keeps it simple and transactional.

6e. **Membership is a soft leave (`left_at` on `team_user`)** — removing a member stamps `left_at` instead of deleting the pivot row, because the tasksheet's per-date history (incl. derived auto-absent rows) needs to know *when* someone was on the team. `Team::members()` now filters to current members (`wherePivotNull('left_at')`), so every existing consumer (assignment options, validation, viewer's team list) treats leavers as gone; `Team::memberRecords()` exposes full history. The sheet lists a leaver's row for dates on or before their leave date; the policy additionally requires current membership for self-edits, so a leaver is read-only on their old rows while leads retain correction rights. Re-adding restores the same pivot row (`left_at` cleared).

6d. **Text cells are rich text; counters are integers** — `plan`, `result`, `comment`, `tickets`, and `feedback` use the Trix + `HtmlSanitizer` pipeline (Attribute setters; visually-empty markup stored as `null` so partial-fill counting stays honest; rendered via an `html()` helper with `prose-notes` styling). `work_points`, `ticket_count`, `ticket_points` remain plain non-negative integers (number inputs, `integer|min:0`, int casts). Supersedes the original "plain textareas" non-goal per product decision.

6c. **Activity logging via `RecordsActivity`, with feedback excluded** — `TasksheetEntry` uses the existing trait (created/updated/deleted entries with causer and field diffs). `feedback` goes into `activityExtraIgnored()`: the activity feed is visible to every authenticated user, so neither feedback snapshots nor feedback old→new diffs may ever be logged. A side effect: a lead updating *only* feedback records no activity at all (the trait skips updates whose diff is empty after ignores) — acceptable, since logging even "feedback changed" would reveal that feedback exists for a row. Description reads "Created/Updated tasksheet row “{member} · {date}”".

6b. **Auto-absent is derived at render, not stored** — when the viewed date is in the past, a dev/QA member row with no saved entry renders an automatic "Absent — not filled" badge. No scheduled job, no materialized rows (nothing to keep in sync, works without a running scheduler). Backfilling is allowed: saving the row (tasks or a leave type) replaces the auto mark. **Late-fill hint**: an entry whose `created_at` falls after the end of its sheet `date` permanently shows a "not added on the operating day" hint — derived via a `wasFilledLate()` helper, no extra column. On-time entries edited later show no hint (creation time, not update time, is what counts).

6a. **Absence is part of the row, not a separate model** — `leave_type` on the entry (`casual`/`sick`/`half_day`, labels via a `LEAVE_TYPES` const map, mirroring `Note::VISIBILITIES`). The row form offers "Working / Casual leave / Sick leave / Half day leave". **Full-day vs half-day split** (`FULL_DAY_LEAVE_TYPES = ['casual','sick']`): a full-day leave renders a badge spanning the task columns and clears the task fields on save (an absent member has no plan/result); a **half-day leave keeps the task fields** — the member still works part of the day and logs tasks — so the grid shows the task cells plus a small "Half day" chip, and fill-status/auto-absent logic treats the row as a working row (`isFullyFilled`/`isPartiallyFilled` gate on `isFullDayLeave()`, not `isOnLeave()`). Markable by the row owner or a lead — same policy as any row save. Rejected alternative: a standalone leave/attendance module — overkill for a sheet cell, and the reference sheet records absence inline too.

7. **Day + team navigation copied from notes/index** — prev/next/today buttons and a date input auto-submitting a GET form; a team select (auto-submit) listing the user's teams first, then other active teams. Sheet header shows "Team {name} — {date label}".

## Risks / Trade-offs

- [Feedback leakage via shared partials or JSON] → Feedback is only ever read in the lead-gated Blade branch; the upsert redirects (no JSON echo); tests assert a dev/qa response body never contains feedback text.
- [Two people editing the same row (member + lead)] → Last write wins on the whole row; acceptable at team scale. Feedback is saved by a lead-only path so a member's save cannot blank a lead's feedback (non-lead saves never touch the column).
- [Teams with no dev/qa members show an empty grid] → Empty state with a hint; leads still see any saved historical rows for the date.
- [Points semantics undefined (who awards Work Points)] → v1 lets the row owner and leads set them, matching the sheet where cells are just typed in; tightening to lead-only is a one-line policy change later.
- [Backfilling can game the record] → Accepted trade-off per product decision: filling late is allowed, but the late-fill hint is permanent (derived from `created_at`), so leads always see the row was not filled on the operating day.
