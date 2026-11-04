## Why

Only admins can delete tasks now, and bulk delete can remove a whole filtered board in one click. A mistake there is permanent, taking subtasks, comments and logged time with it.

## What Changes

- **BREAKING for API clients**: deleting a task moves it to a trash, rather than removing it at once. It still reads as gone everywhere.
- The board offers Undo right after a delete.
- A Trash page lets admins restore a task, or delete it for good.
- Trashed tasks are removed for good after 30 days by the daily prune.
- Confirmations say a deleted task can be restored.

## Capabilities

### New Capabilities
- `task-trash`: soft delete for tasks, restore, purge and automatic removal.

### Modified Capabilities
<!-- None: `openspec/specs/` has no base specs yet. `board-task-delete` is unarchived; its "deleted" now means trashed. -->

## Impact

- **Data**: `tasks.deleted_at`, `deleted_by`, `trashed_with_parent`.
- **Backend**: `Task`, `TaskPolicy`, new `TaskTrashService`, request and controllers, scheduled prune.
- **Frontend**: new `tasks/trash`, Undo on the board, toast action buttons, new confirmation wording.
- **API**: three new admin endpoints; `DELETE` responses are unchanged.
