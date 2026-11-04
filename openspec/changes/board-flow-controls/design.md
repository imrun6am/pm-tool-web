## Context

Filters and the whole-board view mix teams together. A limit only means something for one team's whole column.

## Goals / Non-Goals

**Goals:**
- Make overload and stuck work visible at a glance, with no extra screens.

**Non-Goals:**
- Blocking moves over a limit. People know when an exception is right.
- Full swimlanes, where dragging between rows changes the assignee or priority. That would hide a data change inside a drag, and needs a rebuild of the board script. Grouping within columns gives most of the reading value.
- Grouping on the API. It's a way of viewing; clients already get the fields.

## Decisions

**Limits belong to a team and status,** shown only when the board is filtered to one team with no other filter, since otherwise the count isn't that team's whole column. Done and Archive can't have one.

**Set by the team's lead or an org-level lead,** matching who oversees a team elsewhere.

**Age comes from status history,** the last change, not `updated_at`, so editing a title doesn't reset it. Thresholds of 3, 7 and 14 days are fixed for now. Done and Archive show none.

**A grouped board refreshes after a move.** The headings come from the server, and moving a card within a column can't be placed under the right one by the script alone.

## Risks / Trade-offs

- [Fixed age thresholds won't suit every team] → Simple to make configurable if needed.
- [Dragging within a grouped column can leave its order odd after refresh] → Cards return under their group; the order within groups keeps the saved positions.
