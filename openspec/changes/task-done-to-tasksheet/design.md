## Decisions

### No Claude call for the line itself
The task's title is exact, free and instant, and it can't fail or reword what was done. A move to Done must not wait on or depend on a model. The existing "Draft from board activity" button stays for anyone who wants prose.

### A model observer, not a controller change
Tasks reach Done from the board, the task page, the API and subtask lists. Observing the model covers them all, the same way the status history does.

### Which row
Today's row for the assignee, on the task's team when they are a member. Otherwise it goes to their only team, and if there's no single team it is skipped, because a row on the wrong team's sheet is worse than no line. A full-day leave row is skipped, because saving that row clears its task fields.

### What counts as finishing
A move into Done counts, including a card created in Done by a signed-in user. Archive to Done is a restore, not new work. Done to Archive only files the task, so the line stays. Reopening the same day removes the line; a line from an earlier day is left, because the work was reported on that day.

### The sync can't block the move
A failure is reported and swallowed. Moving a card matters more than the tasksheet line.

## Risks

- A member with the row's editor already open saves what they loaded, which overwrites a line added since. The editor has no merge, so this matches how two people editing a row behave today.
- A row created only by this sync counts as filled in for that day.
