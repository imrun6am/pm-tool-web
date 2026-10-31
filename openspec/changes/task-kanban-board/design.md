## Context

Tasks already exist with a fixed status set (`todo`, `in_progress`, `in_review`, `done`), a `position` column, and a per-authenticated-user collaboration model (anyone can change task status via `PATCH tasks/{task}/status`). This change layers a Trello-style board over those tasks — no new domain concepts, just a new view plus an ordering-aware move endpoint.

## Goals / Non-Goals

**Goals:**
- Four status columns; top-level tasks as cards; drag to change status; reorder within a column; both persist.
- Global and per-release scope; filter by release and assignee.
- Self-contained drag-and-drop (no external JS library), degrading to a readable static board without JS.

**Non-Goals:**
- No new statuses, WIP limits, swimlanes, card editing on the board (cards link to the task page), or subtasks-as-cards.
- No realtime multi-user sync; last write wins.
- No schema change.

## Decisions

**Reuse `tasks.status` + `tasks.position`; add one move endpoint.**
Cards are `rootTasks` (parent_id null). A `BoardController@move(Task)` accepts the target `status` and an `ordered_ids` array — the task ids of the target column in their new order. The controller validates `status` against `Task::STATUSES`, sets the moved task's status, and renumbers `position = index` for every id in `ordered_ids` (scoped to the tasks actually in that column). This keeps the moved column fully consistent; the source column keeps its relative order. Reusing `position` means the release page's task list and the board share one sort key — consistent, no migration.

Alternative considered: a separate `board_position` column — unnecessary; the single `position` already orders root tasks and nothing else depends on a different order.

**Move is `auth`, not `admin`.**
Moving a card is exactly "changing a task's status," which the collaboration model already lets any authenticated user do. The endpoint lives under the `auth` group. The existing `PATCH tasks/{task}/status` remains for the release page's dropdown; the board adds `PATCH board/tasks/{task}` for status+order in one call.

**Drag-and-drop: native HTML5 DnD + a small vanilla script.**
Cards are `draggable`; columns are drop zones. On drop, the script reads the dragged task id and the target column's status and computes the new ordered id list from the DOM, then `fetch`es the move endpoint with the CSRF token from the `<meta>` tag and updates counts. No Alpine store or external library. Without JS, the board still renders every card in its correct column (read-only) — an acceptable graceful degradation.

**Filtering & scope in the controller.**
`BoardController@index` reads `release_id` and `assignee_id` query params, eager-loads `release`, `assignee`, `subtasks`, and a `comments` count, groups root tasks by status, and orders each group by `position, id`. A `release_id` scopes the board (title shows the release name and a back link); the release page links to `board?release_id=…`. Filters combine.

## Risks / Trade-offs

- [Concurrent moves by two users] → Last write wins; acceptable at this scale. Positions renumber on each move so the board self-heals on reload.
- [Sharing `position` with the release list could look arbitrary there] → In practice both order by `position, id`; the board just makes the order intentional. No functional issue.
- [DnD accessibility] → Native DnD isn't keyboard-friendly; mitigated because the same status change is available via the task page and the release-page dropdown, so the board is an enhancement, not the only path.
- [Large boards] → Fine for this scale; if needed later, columns can paginate. Documented, not implemented.

## Migration Plan

1. `BoardController` (index + move) and routes under `auth`.
2. Board view + card partial + drag-and-drop script; nav "Board" link; "Board view" link on the release page.
3. Feature tests (render, filter, move changes status + order, guest blocked, invalid status rejected); build; run suite; smoke-test.
Rollback: remove the routes/controller/view and the two links; no data changes.

## Open Questions

- None blocking. WIP limits and swimlanes are natural follow-ups if the team wants them.
