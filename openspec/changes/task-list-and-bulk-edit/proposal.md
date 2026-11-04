## Why

The board shows work by status, one screen at a time. Nobody can search all tasks, sort by due date or priority, or change many tasks at once, so tidying up after a planning meeting means opening them one by one.

## What Changes

- A new "All tasks" page lists top-level tasks, with search, filters, sortable columns and paging. Developers and QA see their own teams' tasks only.
- Selecting tasks shows a bar to change status, assignee or priority, or add a label, for all of them together.
- `GET /tasks` gains search and sorting, and a new `POST /tasks/bulk` matches the web action.

## Capabilities

### New Capabilities
- `task-list-and-bulk-edit`: the all-tasks page and changing many tasks at once.

### Modified Capabilities
<!-- None: `openspec/specs/` has no base specs yet. -->

## Impact

- **Backend**: `TaskService`, `ReadsTaskFilters`, new `BulkUpdateTasksRequest`, both task controllers.
- **Frontend**: new `tasks/index`, a sidebar item, one new icon.
- **API**: additive parameters and one endpoint.
