## Context

The status history makes every figure computable from one set of tasks. Performance and KPI pages already cover individuals, under stricter access rules. This report deliberately stays at team level.

## Goals / Non-Goals

**Goals:** see bottlenecks and pace at a glance, and spot stuck work while it can still be helped.

**Non-Goals:** per-person views, custom date ranges, sprint charts.

## Decisions

- **Top-level tasks, Archive excluded.** Same unit as the release figures.
- **Work finished before the window is left out**, otherwise the Done band would dwarf everything else.
- **Weekly snapshots at the end of each week**, with the current week as of now.
- **Cycle time runs from the first move to In Progress to the last move to Done.** Tasks with no start, or with no recorded finish, count as finished but have no duration.
- **Aged work compares against the 85th percentile.** It's derived from the team's own history, so there's no threshold to configure. Its start is the first move to In Progress, or creation if there's none.
- **Access**: `full-access` (leads and viewers). Developers and QA see only their own teams elsewhere, and this page has no such scoping, so they don't get it. Tasks are named but people aren't, so viewers can have it.
- **At most 300 dots**, the most recent. Percentiles still use every task.
- **Colors**: To Do `#a78bfa`, In Progress `#2563eb`, In Review `#f59e0b`, Recheck `#be123c`, Done `#10b981`. The board's own status colors failed the colorblind check, so these were picked to pass. The validator warned on contrast, so every band has a legend entry, a hover readout and a table.

## Risks / Trade-offs

- Everything is computed per request. A year of history isn't read, only the window plus open work, so this should stay fast. If it doesn't, cache by team and week.
- Moving a task through a status and back within a week doesn't appear in that week's snapshot. That's acceptable for a trend.
