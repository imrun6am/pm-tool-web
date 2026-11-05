## Why

A tasksheet plan often has main tasks with subtasks under them, but "Add to board" makes every line its own top-level card. Two things cause this: the extractor can only return a flat list, and `PlanToBoardService::plainText()` loses list nesting before the AI reads the plan (`<li>Main<ul><li>Sub` becomes `- Main- Sub`). Fifteen of the last 400 plans on the dev database use nested lists, and 75 put a line of text above a list.

## What Changes

- **The plan's hierarchy reaches the board.** A main task in the plan becomes a board task, and its sub-items become that task's subtasks.
- **`plainText()` keeps nesting.** Nested list items get their own indented line. Flat text is unchanged, and the other AI features that read rich text benefit too.
- **The extractor returns `subtasks`** for each task, and is shown the member's existing subtasks and the other open main tasks in the same release (or team, when there is no release).
- **New work attaches to a main task already on the board**, including a teammate's. Only the new subtasks are created.
- **A subtask whose main task is not on the board creates the main task too.**
- **Nothing is duplicated when pressing again**, including over cards made flat by the old behaviour. Those cards are left as they are.
- **New API endpoint** `PUT /api/v1/tasksheet/entries/board`, so the desktop client gets the same behaviour. The action was web-only.
- **The drawer's hint and the result message mention subtasks.**

## Capabilities

### New Capabilities

<!-- None. -->

### Modified Capabilities
- `tasksheet-plan-to-board`: creates subtasks from the plan's hierarchy, attaches to existing main tasks, dedupes across levels, and is available on the API.

## Impact

- `app/Services/Board/` (service, extractor interface and implementation).
- `app/Http/Controllers/TasksheetController.php`, `app/Http/Controllers/Api/V1/TasksheetController.php`, `routes/api/v1/tasksheet.php`, `docs/api-v1.md`.
- `resources/views/tasksheet/_row.blade.php` (hint text only).
- No migration: subtasks already exist as `tasks.parent_id`.
- The AI request gains a schema field and more context, so a call costs slightly more.
