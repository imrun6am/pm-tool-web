## Context

`task-done-to-tasksheet` writes finished tasks into the result, and `ai-work-assists` drafts plan and result text with Claude. This change covers what's left and doesn't overlap them.

## Goals / Non-Goals

**Goals:**
- A free, instant way to put a real task into the plan.
- Hours visible where leads already review the day.

**Non-Goals:**
- Syncing a plan back to the board. "Add to board" already goes that way.
- Replacing work points with hours. They measure different things.

## Decisions

**The picker is client-side.** It inserts into the open editor, so unsaved edits are kept and nothing is saved until the person saves the row. The plan stays free text.

**One query for all rows.** `openTasksFor` groups by assignee. Only rows the viewer can edit get tasks: every row for a lead, their own for a member. A teammate never sees someone else's list here.

**Hours sum across all tasks that day,** whichever team the task is on, because the row is about the person's day.

**Viewers don't get hours,** matching the time logging rule.

## Risks / Trade-offs

- [The disabled check looks for the key in the saved plan, so editing the text can re-enable a task] → Harmless: at worst a line is added twice.
