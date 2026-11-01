## Context

`DashboardController` branches on `User::hasLimitedAccess()`. Developers and QA get `dashboard-member` built from `DashboardService::memberSnapshot()`, and the API mirrors that under `mode: member`. Tasks have no `started_at` or `completed_at` columns. The `RecordsActivity` trait logs every `updated` event with `{old, attributes}` diffs, so status history is in `activities` (`subject_type = App\Models\Task`, `properties->attributes->status`). Tasksheet rows carry `ticket_count`, `ticket_points`, `work_points`, `leave_type` and the three-valued `standup_attended`.

## Goals / Non-Goals

**Goals:**
- personal, data-backed metrics with a comparison against the member's own past;
- encouraging framing;
- one service shared by web and API;
- bounded query count.

**Non-Goals:**
- new columns or backfills;
- team or colleague comparisons;
- leads' dashboards (a possible follow-up);
- caching (see decision 6);
- a JS chart library.

## Decisions

### 1. Completion and start times come from the audit trail
For each of the member's tasks, the service reads its status-change activities in one query, using a sub-select on the task ids rather than a large `IN` list.
- **completed_at:** the *last* change into `done` from an open status. Archive → Done is a restore, not a new completion.
- **started_at:** the *first* change to `in_progress`.
- A task counts as completed only while its current status is `done`, so reopened work drops out. **Archived tasks are never counted** (`Task::UNCOUNTED_STATUSES`): they appear in no total, rate, trend, streak, heatmap or personal best, and the status breakdown covers counted statuses only.
- **Fallbacks**, for tasks with no transition logged (seeded rows, or a task created as done): `created_at` when it was created done, otherwise `updated_at`.
- **Cycle time** is `completed_at − started_at`, and only when both exist. **Lead time** is `completed_at − created_at`.

*Alternative considered:* adding `started_at`/`completed_at` columns. That would be more precise, but it needs a migration and a backfill, and other sessions are running migrations against the shared DB right now. Derivation needs neither.

### 2. Scope = tasks currently assigned to the member
This matches the existing "Open tasks" count (subtasks included). Work that was reassigned away counts for the new assignee. This is accepted: there is no assignment history for "who did it" beyond the causer, and the causer is often QA moving the card.

### 3. Periods compare like for like
`MemberPeriod` resolves `week`, `month` or `quarter` (default `month`) to a span that runs from the start of the period to today. The comparison is the *same elapsed span* of the previous period: Nov 1–5 is compared with Oct 1–5, not with all of October. Otherwise every early-month view would look like a decline.
- **Web:** silently falls back to the default for an invalid value.
- **API:** validates `period` (422).

### 4. Encouraging, honest metrics
- Rates are `null` when the denominator is 0, and the UI shows "No data yet" rather than 0%.
- A higher-is-worse metric (cycle time) inverts its delta tone.
- A decline is shown in neutral slate, never in red.
- The **weekly pace** is the ceiling of the member's average over the previous 8 full weeks (minimum 1). It is null with no history, so it is the member's own norm, not a target set by anyone else.
- The **streak** counts consecutive weekdays with a completed task or a fully filled tasksheet row. Weekends and full-day leave never break it, and today doesn't break it until the day is over.
- The **tasksheet fill rate** uses weekdays only, from the period start (or team join) to yesterday, plus today once it has been filled. Full-day leave days are excluded.
- **Standup rate** = attended / marked, ignoring unmarked rows and full-day leave.

### 5. Query budget
The service runs about 7 queries, whatever the volume:
- the member's tasks, with their release;
- status activities;
- tasksheet rows in the window, selecting only the needed columns and **never `feedback`**;
- comment counts grouped by day;
- attended meetings in the window;
- the team join date;
- the open tasks list (reused from `memberSnapshot`).

Everything else is computed in PHP over these bounded sets. The window is 26 weeks for the heatmap, or the previous period's start if that is earlier.

### 6. No cache
The queries are bounded and indexed per user. A 60-second cache would hide the task the member *just* finished, which is the very moment the page should celebrate. Revisit only if profiling shows a need.

### 7. Presentation
- **Charts:** SVG and CSS only, in partials alongside the existing `hbar-chart`, `spark-line` and `radar`.
- **Accessibility:** every chart has `role="img"` and an `aria-label` summary, and heatmap cells have `<title>` tooltips.
- **Theme:** the app has no dark mode, so the light tokens are used.
- **API presenter:** maps the service's camelCase keys and Carbon values to snake_case and ISO dates, as the planning presenter does.

## Risks / Trade-offs

- **Fallback timestamps are approximate** for tasks without history. *Mitigation:* fallbacks never feed cycle time; they are used for counts and trend only.
- **JSON path query** (`properties->attributes->status`) differs per driver. *Mitigation:* Laravel compiles it for both MySQL and SQLite, and the tests run on SQLite.
- **A long page** on phones. *Mitigation:* sections stack in priority order (momentum, today, focus, then the analytics).
