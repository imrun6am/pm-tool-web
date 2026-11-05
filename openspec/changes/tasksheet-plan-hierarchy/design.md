## Context

A tasksheet row is one member's day. Its plan is a single rich-text field, so there are no separately selectable rows: the hierarchy lives in the plan's text, and the AI reads it. Subtasks are `tasks` rows with `parent_id`, one level deep, enforced by `TaskService::createSubtask()`. The board shows top-level tasks as cards with a subtask progress bar, and the task page lists subtasks.

## Decisions

### The AI decides the structure, with nesting as its clearest signal
A deterministic list parser would miss the other ways people write this ("Build refund flow" above a bullet list, or 1 and 1.1 numbering), and titles are rewritten by the AI anyway. So `plainText()` preserves nesting and the prompt says what counts: an indented item is always a subtask of the item above it, and an actionable line above a list is a main task. A bare project name above a flat list stays a heading, so it does not become a card, as today.

### Fixing `plainText()` rather than adding a second converter
Its contract is already "one line per block or list item", and nested lists break that. Flat input gives the same output as before.

### Attaching to an existing main task
The AI returns the main task with its existing title copied exactly and only the new sub-items. The service matches titles (case and whitespace ignored) in this order:
1. The member's own open main tasks, and main tasks where they hold an open subtask.
2. The member's existing subtasks. Nesting is one level, so new items go to that subtask's parent.
3. Other open main tasks in the same release (or the row's team when there is no release), limited to what the acting user may see. Only used when the item has subtasks.

A plan line with no sub-items that matches only a teammate's task still creates the member's own card, as today: two people can do same-named work.

### A done or archived main task is not attached to
It stays consistent with "done work can come round again". The main task is created anew with its subtasks.

### Subtask duplicates are checked per parent, at any status
"Write tests" can fairly appear under two main tasks. Under one open parent, a matching subtask counts as already there even when it is done, because the parent is still in progress and the plan is simply being pressed again. Done top-level tasks keep their existing behaviour.

### Old flat cards are left alone
A sub-item matching one of the member's open top-level cards is skipped, not moved. Re-parenting someone's card as a side effect of pressing a button would surprise them and could drop it from their view.

### Fields
Subtasks take the same fields a card gets today: To Do, assigned to the row's member, created by the acting user. Their release and team come from the parent through `TaskService::createSubtask()`. A tasksheet row has no hours, dates or priority, so none are set.

### API
`PUT /api/v1/tasksheet/entries/board` shares `TasksheetBoardRequest`, the policy, and a new `PlanToBoardService::attempt()`, which also produces the message, so both surfaces say the same thing. The row is saved even if the AI fails, and the API then answers 503 with the saved entry and the same message, so a client can't mistake it for success.

### Caps
25 main tasks as before, and 25 subtasks per main task.

## Risks

- **The AI could reword an existing title**, which would create a second main task instead of attaching. The prompt requires an exact copy. Checked with a real call.
- **A subtask under a teammate's card won't appear on the member's "My tasks" board view**, because that view filters cards by the card's assignee. They are notified, it appears in their open tasks on the tasksheet, and it can be found by filtering the task list. Changing the board filter is out of scope.
