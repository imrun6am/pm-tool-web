## Why

Developers and QA open the board and the task list to find their own work, and have to filter down to it on every visit.

## What Changes

- A Developer or QA user who opens the board or All tasks with no filters sees only the tasks assigned to them, with My tasks selected.
- They can still choose Anyone or turn My tasks off to see everything they are allowed to see.
- All tasks gains the My tasks button the board already has, for every role.
- Links that carry a filter, such as a release, team or label, are not narrowed. Leads and viewers keep seeing everything by default. The API is unchanged.

## Capabilities

### New Capabilities
- `my-tasks-default`: Developers and QA open the board and task list on their own tasks.

### Modified Capabilities
<!-- None: `openspec/specs/` has no base specs yet. -->

## Impact

- `ReadsTaskFilters`, `BoardController`, `TaskController`, `TaskService::ANYONE`
- `board/index`, `tasks/index` and a shared `partials/my-tasks-toggle`
