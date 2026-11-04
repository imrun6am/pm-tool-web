## Context

`MemberAnalyticsService` and `PerformanceAnalytics` already compute most of these figures, but for one person or one team at a time, over fixed windows. Calling them per person would be an N+1 across a whole organisation, so this report loads each kind of fact once for the period and adds it up in PHP.

## Decisions

### Teams stand in for departments

Nothing in the schema or the code mentions a department. Teams are the closest concept, and they carry a lead, members and a colour.

### Every piece of work belongs to exactly one team

A task, time entry or request counts for its own team. With no team, it counts for the person's team if they are on exactly one, which matches `PerformanceAnalytics`. Otherwise it counts under "No team". This means team rows add up to the all-teams total, and a person on two teams isn't counted twice.

Someone who worked on a team without being a member shows on that team tagged "Not a member". Someone who left during the period is tagged "Left".

Some figures can't be split this way and stay whole-person: calendar meetings, meeting notes, comments and AI uses. A team row sums them over its members, so a person on two teams counts in both.

Tasksheet days are per team, because each team has its own sheet. A person on two teams owes a row on each, so a team's days can add up to more than the all-teams figure, which counts each calendar day once.

### Rates come from summed parts

A team's on-time rate is on-time tasks over dated tasks across the team, never an average of its members' rates. The same goes for every rate here. A median is taken over all the values, not over the members' medians. The one exception is a team's rating, which is the average of its rated members, as on the Performance page.

### Too little data is a value of its own

`Metric::measure()` returns `{value, sample, enough}`. Below the minimum, the page shows "Too few" and the sample size, the CSV leaves the rate blank but keeps the counts, and the API returns all three.

| Figure | Minimum |
| --- | --- |
| On time, cycle, lead, rework, estimate accuracy | 3 tasks |
| Tasksheet filled, plan with result | 5 days |
| Standup | 5 marked days |
| Rating | Half the competencies that apply to the person |
| KPI | 5 analysed days |

### Definitions reuse the existing ones

- **Completed, started, reworked**: `TaskCompletions`, including the rule that a task with no logged move into Done counts but never gives a duration. Archived tasks never count.
- **Tasksheet days owed**: working days (`WorkingWeek`) within the membership, without full-day leave, up to yesterday, plus today once filled. Only developers and QA owe rows, as on the tasksheet, and only until they left, were deactivated or were deleted.
- **Estimate accuracy**: the `TimeReportService` rule. Tasks finished in the period that have an estimate and logged time.
- **Rating**: the `PerformanceAnalytics` weighted average, excluding unrated competencies.
- **KPI**: the latest completed KPI review overlapping the period. It's AI-drafted and labelled so.

### Blocked time comes from the activity log

Tasks store only the current `blocked_at`. When a flag is cleared, the activity log keeps both the old value and the moment it changed, so that row is a complete interval. Tasks still flagged supply the open intervals. Two limits follow:

- The log is pruned after `logging.retention_months`, so older time can't be seen. The page says so when the period reaches back further.
- Waiting on another task has no history. It counts in "Blocked now" but not in blocked days.

Blocked days are calendar days, so they can be compared with cycle time.

### Plan against result

Plan and result are free text, so they can't be compared without AI. The report gives what's measurable: of the days with a plan, how many also have a result. The KPI review's AI-counted planned and completed tasks sit next to it when one exists.

### Open work is "now", not the period

The board only stores current status, so open, overdue and blocked counts are always as of today. They are labelled "now", and the period filter doesn't move them.

### Private events stay private

A private event is seen only by its creator and attendees, and an admin isn't one of them. Private events are left out of the meeting counts. Lead-only tasksheet feedback and rating notes are never read.

### Columns live in one place

`PeopleReportColumns` lists every figure with its group, label, type and path. The page, the PDF and the CSV all read it, so they can't disagree.

### Room for the scorecard

The set-aside scorecard has five categories: delivery, quality, ownership, AI adoption and collaboration. A person's record is already grouped this way (`delivery`, `quality`, `workload`, `collaboration` with `aiUses`, and so on), so a later change can weight the parts without new queries. No score is computed now.

### Cost

A fixed number of queries whatever the head count. Tasksheet rows are read through a cursor with rich text reduced to a filled or empty flag, so a year of rows doesn't load everyone's HTML. The PDF renders in the request: it holds only aggregates, so it doesn't need the tasksheet export's queue.

## Risks

- **It reads like a league table.** Rows default to name order and no rank is shown. Sorting is the admin's choice.
- **Team-less tasks shift a person's team total** when they join a second team, because the "exactly one team" rule stops applying. Acceptable: such work moves to "No team", where it's still visible.
