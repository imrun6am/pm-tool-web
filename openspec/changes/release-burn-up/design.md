## Context

`task_status_changes` records every move, so a task's status on any past day is known. The chart needs no snapshots.

## Goals / Non-Goals

**Goals:** see scope and finished work over time, on every release, with no setup.

**Non-Goals:** weighting by hours. Counting tasks works whether or not they're estimated, and the forecast uses the same unit.

## Decisions

- **Top-level tasks, without Archive or the trash.** This matches every other release figure. Subtasks would count the same work twice.
- **At most 25 points.** A short release is drawn daily and a long one evenly spaced, always ending on the last day, so the chart stays readable without weekly bucketing rules.
- **A task with no history** counts as done from its last save if it's Done now. These are rows older than the status history.
- **The line chart is HTML over a stretched SVG.** Lines stretch with the card and labels stay crisp at any width, with no chart library and no JavaScript. Hover is CSS. Direct labels sit only on the last point, and a table holds every number.
- **Colors**: `#0b63f6` and `#d97706`, a pair that passed the colorblind validator. Text stays in the text tokens.

## Risks / Trade-offs

- Loading each task's history on the page costs one extra query, and the set is limited to one release, so that's fine.
- Dark mode isn't covered because the app has no dark mode.
