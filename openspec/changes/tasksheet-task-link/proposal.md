## Why

The tasksheet and the board describe the same work, and people type it twice. `task-done-to-tasksheet` covers finished work and `ai-work-assists` drafts text, but picking today's plan still means retyping task titles, and logged hours don't show on the sheet at all.

## What Changes

- The row editor lists the person's open tasks, most urgent first. Choosing one adds it to the plan with its key, with no Claude call. Tasks already in the plan are disabled.
- Each row shows the time that person logged on tasks that day. The API returns it as `logged_minutes`. Viewers don't get it.

## Capabilities

### New Capabilities
- `tasksheet-task-link`: picking open tasks into the plan, and logged hours on rows.

### Modified Capabilities
<!-- None: `openspec/specs/` has no base specs yet. -->

## Impact

- **Backend**: `TasksheetService`, both tasksheet controllers.
- **Frontend**: `tasksheet/_row`, new `tasksheet/_plan-tasks`.
- **API**: one additive field on `GET /tasksheet` rows.
