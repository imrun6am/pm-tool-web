## Why

The kanban board works, but it looks flat and dated:
- the columns are uniform gray;
- the cards use emoji icons, have no due-date urgency, and give no hover or drop feedback;
- moving a card is mouse-only and silent, falling back to a full page reload on error;
- the Archive column takes as much space as active work.

The board is where developers and QA spend their day, so it should feel vibrant, professional and easy to use.

## What Changes

- **Board summary:** above the columns, the total task count, % done, and a segmented progress bar in status colors.
- **Quick filters:** a "My tasks" toggle next to the existing release and assignee filters, keeping the existing `release_id` / `assignee_id` parameters.
- **Columns:**
  - each gets a status-colored top accent, a tinted header and count pill, and a soft status-tinted background;
  - a clear drop-target highlight while dragging;
  - a dashed drop zone when empty.
  - **Archive** starts collapsed into a slim rail that expands on click (remembered per browser).
- **Cards:**
  - a project-color accent stripe and a release chip;
  - outline icons instead of emoji;
  - a due-date pill (overdue red, due today amber), a colored phase chip, and a subtask progress mini-bar;
  - the assignee avatar;
  - hover lift and focus ring.
- **Better moving:**
  - every card gets a keyboard-accessible "Move to…" status menu, so drag-and-drop is no longer the only way;
  - a success toast confirms each move;
  - a failed move shows an error toast and puts the card back.
- **Unchanged:** routes, the `board.move` and `board.tasks.store` endpoints, permissions and the data passed to the view.

## Capabilities

### New Capabilities
- `kanban-board`: board summary, quick filters, column and card presentation, the collapsed archive, drag feedback, the keyboard-accessible move menu, and move confirmations.

### Modified Capabilities

<!-- None (no archived specs). -->

## Impact

- **Views:** `board/index.blade.php` (layout and the move script), `partials/board-card.blade.php`.
- **CSS:** board column and card classes in `resources/css/app.css`.
- **Tests:** new feature tests for the summary, the My tasks toggle and the move menu. `KanbanBoardTest` passes unchanged.
