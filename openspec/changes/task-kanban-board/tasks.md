> Implemented and verified. 62 tests pass (6 new board tests). Live smoke test confirmed the board
> renders four columns with cards, a Developer moved a card to Done via the endpoint (status
> persisted), and release filtering works.

## 1. Controller & routes

- [x] 1.1 `BoardController@index`: read `release_id` + `assignee_id` filters; load root tasks with release/assignee/subtasks + comment count; group by status ordered by position, id
- [x] 1.2 `BoardController@move(Task)`: validate `status` in set + `ordered_ids` array; set status; renumber positions for the target column
- [x] 1.3 Routes under `auth`: `GET board` (board.index), `PATCH board/tasks/{task}` (board.move)

## 2. Views

- [x] 2.1 Board view: four status columns with counts, filter controls (release, assignee), release-scoped title + back link, empty-column placeholders
- [x] 2.2 Card partial: title (link to task page), release, assignee, due date, phase, subtask progress, comment count
- [x] 2.3 Drag-and-drop script (vanilla): draggable cards, droppable columns, POST move with CSRF, update counts; graceful static fallback
- [x] 2.4 Nav "Board" link; "Board view" link on the release page (scoped to that release)

## 3. Test & verify

- [x] 3.1 Feature tests: board renders for a user; filter by release/assignee; move changes status; reorder persists; guest blocked; invalid status rejected
- [x] 3.2 `npm run build`, run full test suite, live smoke test (drag simulated via the move endpoint), then check off tasks and `openspec validate`
