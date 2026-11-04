## Why

People finish tasks on the board and then type the same work again into their tasksheet's day-end result. Finished work gets left out, which also weakens KPI reports and standup digests built from the tasksheet.

## What Changes

- When a task moves to Done, however that happens, a line naming it is added to the assignee's day-end result for today.
- Reopening the task the same day takes that line back out, unless the member has since rewritten it.
- "Draft from board activity" is told what the result already says, so it doesn't repeat those lines.

## Capabilities

### New Capabilities
- `task-done-to-tasksheet`: finished tasks are added to the assignee's day-end result.

### Modified Capabilities
<!-- None: `openspec/specs/` has no base specs yet. -->

## Impact

- **Backend**: a new `TasksheetResultSync` service and `TaskTasksheetObserver`; `TasksheetDrafter` and its endpoint take the current result.
- **Frontend**: the draft button sends the current result.
- **API**: no payload changes. Moves through the API update the tasksheet too.
- **Cost**: no Claude call. The line is written directly.
