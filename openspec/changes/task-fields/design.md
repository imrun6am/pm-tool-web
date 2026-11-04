## Context

Time logging is in place, and flow reports, capacity and GitHub links all need more from a task: an estimate to compare against, a priority to order by, a label to group by and a key to reference.

## Goals / Non-Goals

**Goals:**
- Optional fields that never get in the way of adding a task quickly.
- Filters that work the same on web and API.

**Non-Goals:**
- Custom fields defined by users.
- Per-team or per-project labels. One shared list stays simple for this team size.
- Sorting the board by priority. Column order remains the team's own drag order.

## Decisions

**Priority is a fixed list on the model.** Four values cover it, and reports can rely on them. It follows how statuses and phases are modelled.

**The key is derived, not stored.** `PM-` plus the id can't drift or collide, and needs no migration. The prefix is one constant, since this app runs one workspace.

**Estimates use minutes and the existing `Duration` parser.** The same text as time logging works, such as "4h 30m". The parent's estimate adds its subtasks' to its own, matching how logged time rolls up.

**Leaving `label_ids` out keeps labels.** That lets partial API updates and other task forms leave them alone. Unticking every box on the task page sends nothing, so the page adds a `label_ids_sent` marker that turns it into an empty list.

**Labels use checkboxes on the task page.** The properties column submits through `form="task-form"`, which the multi-select component's hidden inputs don't support, and a short list reads well as checkboxes.

**Priority and labels are absent from quick add.** Adding a card stays one field; people set the rest later.

## Risks / Trade-offs

- [Label changes aren't in the activity log, because they don't change the task's own row] → Accepted for now; priority and estimate are logged as normal.
- [The filter row is getting long] → It wraps within the header. Saved views will reduce how often people need it.
