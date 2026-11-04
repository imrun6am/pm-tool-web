## Why

Opening a task from the board means leaving the board, then finding your place again. Small updates such as logging time, changing priority or adding a comment shouldn't cost a page change.

## What Changes

- Clicking a task title on the board or the all-tasks page opens it in a side panel. A modified click still opens the full page in a new tab.
- The panel edits the main fields, and handles dependencies, time and comments, each updating in place.
- The page behind refreshes when the panel closes, if anything changed.

## Capabilities

### New Capabilities
- `task-side-panel`: viewing and editing a task in a drawer from the board and task list.

### Modified Capabilities
<!-- None: `openspec/specs/` has no base specs yet. -->

## Impact

- **Backend**: new `tasks.panel` route and action, new `KeepTaskPanelOpen` web middleware. Existing actions are unchanged.
- **Frontend**: new `tasks/panel`, `tasks/_panel-drawer`, `resources/js/task-panel.js`; links on board cards and the task list.
- **API**: none. Clients already have these actions.
