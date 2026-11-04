## Context

Tasks have estimates and logged time, the tasksheet records leave, and releases record off-days. Nothing brings them together.

## Goals / Non-Goals

**Goals:**
- An honest view of who is over or free in the coming weeks.

**Non-Goals:**
- Per-person working hours or part-time schedules. One setting covers the team for now.
- Spreading a task's estimate across the weeks before it's due. A due date is the only scheduling data tasks have.
- Changing work from this page.

## Decisions

**The working week is configuration.** `PLANNING_WEEKEND_DAYS` and `PLANNING_HOURS_PER_DAY`, defaulting to Friday, this team's weekend, and 8 hours, so it can match how the team actually works.

**Load is the estimate less time logged on that task,** never below zero. It counts every open task assigned to the person, on any team, because a person's week isn't split by team. Parents and subtasks each count their own estimate, matching how the time summary adds them.

**Unestimated work is shown, not guessed.** An average would look precise and be wrong.

**Weeks start at the current one.** It's for planning ahead; past weeks would mix overdue work with history.

**Leads only.** Team leads see the teams they lead, matching performance access. This is people-level data, so viewers don't get it.

## Risks / Trade-offs

- [Estimates are often missing at first, so load looks low] → "Not estimated" counts make that visible.
- [Release off-days apply to the whole team even if only one release marked them] → Accepted; teams rarely run overlapping calendars.
