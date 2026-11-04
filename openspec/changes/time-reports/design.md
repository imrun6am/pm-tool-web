## Context

`TaskTimeLog` holds each entry and `Task.estimate_minutes` the estimate. Showing hours per person is sensitive, so it follows the same lead-only rule as capacity.

## Goals / Non-Goals

**Goals:** where the hours went, and whether estimates hold up.

**Non-Goals:** timesheets, billing, approval flows, or any link to scoring people.

## Decisions

- **Access**: `lead` middleware. Org-level leads see everything. A team lead sees the teams they lead, and naming another team is refused rather than silently emptied.
- **Accuracy uses tasks that are Done, moved to Done in the period, and have both an estimate and logged time.** Anything else would compare against nothing. A task's own logs only, so a parent and its subtasks each stand alone.
- **Deleted tasks are left out**, the same as every other report. Hours from people who have left stay, under their name.
- **The default period is the last 30 days.**
- **CSV cells that could run as a formula are prefixed with `'`**: anything starting `=`, `+`, `-`, `@`, tab or carriage return. Titles and notes are free text, and leads open these in spreadsheets.
- **Streamed in chunks** so a long period doesn't load every row.

## Risks / Trade-offs

- A ratio can be misleading if few tasks have both figures, so the page shows how many were compared next to it.
- Showing hours per person invites misuse. The page states its purpose and nothing else reads this service.
