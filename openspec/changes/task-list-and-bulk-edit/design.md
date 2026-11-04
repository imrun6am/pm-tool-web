## Context

`TaskService::filtered` already backs `GET /tasks`, with team scoping built in. Priority, labels and blocked filters exist from earlier changes.

## Goals / Non-Goals

**Goals:**
- One query path for the web list and the API.
- Bulk changes that behave exactly like changing each task by hand.

**Non-Goals:**
- Bulk delete on this page. The board has it; trash and undo come next, and this page can adopt it then.
- Selecting across pages. A filter narrows the list first, and 50 per page covers normal use.
- Bulk editing of releases or due dates.

## Decisions

**Each task is saved through the model.** Status history, the activity log and notifications all follow, at the cost of one write per task. The request caps a change at 200 tasks.

**All or nothing.** It runs in a transaction, and one task out of reach gives a 403 with nothing changed.

**Blank means no change, `none` clears.** A form select needs an empty "no change" option, and null can't also mean clear. `none` is what the filters already use.

**A new assignee must suit every task,** using the same rules as assigning one: active, on a linked release's team, and a teammate when the person acting is a developer or QA.

**Sorting follows meaning.** Status and priority sort in their own order, and empty values come last in either direction. Unknown sort values are ignored, and only known columns reach the query.

## Risks / Trade-offs

- [Bulk assign sends one notification per task] → Accepted. A digest could group them later.
