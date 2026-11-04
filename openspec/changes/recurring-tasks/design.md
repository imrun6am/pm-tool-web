## Goals / Non-Goals

**Goals:** dependable repeat work that behaves like any other task.

**Non-Goals:** custom intervals such as every two weeks; editing a rule in the UI (remove and add it again); skipping a run when the last task is still open.

## Decisions

**A missed period makes one task.** After scheduler downtime or a long gap, a flood of copies would be worse than one. Resuming a paused rule skips what was missed in the same way.

**Running twice is safe.** `next_run_on` moves on in the same transaction that creates the task.

**Daily means working days,** using the configured weekend from the capacity settings.

**Monthly keeps its day.** `anchor_day` remembers the 31st, so February's 28th doesn't drag later months to the 28th.

**The rule's creator files the task.** A deactivated assignee is left off, since they can't do the work. Tasks made this way have no signed-in actor, so no "assigned" notice goes out; the due reminder still does.

**Leads only,** across all teams, to keep it simple.

## Risks / Trade-offs

- [Nothing runs if the scheduler isn't set up] → It's already needed for reminders and exports.
