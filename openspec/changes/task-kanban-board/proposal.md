## Why

Tasks today live in a list on each release page. Teams work tasks by status (To Do → In Progress → In Review → Done), and a Trello-style board makes that flow visible and lets anyone move work forward by dragging a card. This adds a Kanban board over the existing tasks.

## What Changes

- Add a **Kanban board** with four columns matching task statuses: **To Do, In Progress, In Review, Done**.
- Cards are the **top-level tasks** (subtasks appear as a done/total progress badge on the card). Each card shows title, release, assignee, due date, phase, subtask progress, and comment count.
- **Drag a card between columns to change its status**, and reorder cards within a column; the new status and order persist. Any authenticated user can move cards (collaboration).
- The board is available **globally** (all tasks) and **scoped to one release** (a "Board" link from the release page), and can be **filtered by release and assignee**.
- Add a **Board** link to the main navigation.

## Capabilities

### New Capabilities
- `task-kanban-board`: A status-column board of tasks with drag-and-drop to change status and order, available globally and per-release, filterable by release and assignee.

### Modified Capabilities
<!-- None as a formal delta. Reuses existing task status + position; the board is an
     additional view over task-management, which is not yet archived to openspec/specs. -->

## Impact

- **No schema change** — reuses `tasks.status` and the existing `tasks.position` column for in-column ordering.
- **Controller/routes**: a `BoardController` (index with filters) and a task **move** endpoint (`status` + new column order) under the `auth` group.
- **UI**: a board view with drag-and-drop (vanilla JS, no external library), a card partial, a nav "Board" link, and a "Board view" link on the release page.
- **Tests**: board renders and filters; moving a card changes status (and is blocked for guests).
