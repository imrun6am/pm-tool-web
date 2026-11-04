## Context

Tasks have no time data, and leads need to correct mistakes without opening hours to every colleague's edits. The product owner asked for hours on tasks and accepted the proposed editing and visibility defaults.

## Goals / Non-Goals

**Goals:**
- Quick entry from the task page, and the same on the API.
- Totals that roll up from subtasks to the parent.

**Non-Goals:**
- A start and stop timer.
- Feeding hours into performance scores or KPI reports. Hours used as a score invite logging to look good.
- Estimates and reports, which come with the task fields and time reports.

## Decisions

**One table, in minutes.** Whole minutes avoid rounding drift when summing. `App\Support\Duration` handles "1h 30m" text; the API also accepts `minutes`.

**Entries belong to the person logging them.** The user comes from the session, never the request, so nobody can log time for someone else. A lead correcting an entry keeps its original owner.

**Rules live in the policy and request.** `TaskTimeLogPolicy` decides who may act; `TaskTimeLogRequest` validates. A 7-day backdating limit matches the edit window, and one person's day can't exceed 24 hours, which catches typing mistakes such as "90" read as hours.

**A bare number means hours.** People think of time in hours here, and an over-large value is refused, not stored.

**Viewers see totals only.** Entries name people, and viewer access elsewhere leaves out people-level figures. The API omits `entries` for them rather than answering 403, as other restricted fields do.

**Time goes with its task.** The foreign key cascades, and the activity log keeps the audit record.

## Risks / Trade-offs

- [Self-reported hours are approximate] → Reports should treat them as estimates of effort, not attendance.
- [A task with over 200 entries lists only the newest] → Totals still cover everything.
