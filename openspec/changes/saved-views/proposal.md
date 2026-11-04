## Why

People apply the same filters every day, such as their team's urgent bugs. Setting them up each time wastes effort, and a team has no way to share a useful view.

## What Changes

- On the board and the all-tasks page, a Views menu lists saved filter sets and saves the current filters under a name.
- Views are personal. Leads can share one with everyone.
- Owners remove their own views, and leads can remove shared ones.
- New web and API endpoints.

## Capabilities

### New Capabilities
- `saved-views`: named, optionally shared filter sets for the board and the task list.

### Modified Capabilities
<!-- None: `openspec/specs/` has no base specs yet. -->

## Impact

- **Data**: new `saved_views`.
- **Backend**: `SavedView`, `SavedViewPolicy`, `SavedViewService`, request, web and API controllers, resource.
- **Frontend**: new `partials/saved-views` in the board and task list headers.
