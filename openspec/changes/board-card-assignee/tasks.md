## 1. Backend

- [x] 1.1 Accept `assignee_id=none` in `BoardService::columns()` and both `BoardController::index` actions
- [x] 1.2 Split `TaskService::assignableUsers()` and add `cardAssignees()`
- [x] 1.3 Add `BoardService::assign()`, changing only the assignee
- [x] 1.4 Add `AssignTaskRequest` on top of `TaskRequest`, using the task's own release
- [x] 1.5 Add `assignees` and `assign` to the web and API `BoardController`s, with their routes

## 2. Board UI

- [x] 2.1 Add the Unassigned option and name it in the select-all scope
- [x] 2.2 Add `partials/board-assignee` and use it on the card
- [x] 2.3 Add `ss:open` and `ss:close` to `searchable-select.js`
- [x] 2.4 Add the board script: fetch the list, open the dropdown, save, swap the button, remove a card that no longer matches, failure toasts
- [x] 2.5 Add the button and dropdown styles, keeping the card's height

## 3. Docs and tests

- [x] 3.1 Document the filter value and the endpoints in `docs/api-v1.md`
- [x] 3.2 Feature tests: filter alone and with team, assign, clear, status kept, disallowed user and role, release override, deactivated user, picker contents
- [x] 3.3 Add filter and assign cases to `WebApiParityTest`
- [x] 3.4 Run the full PHPUnit suite and Pint
- [x] 3.5 Check the UI at laptop width against a scratch SQLite database

## 4. Wider columns

- [x] 4.1 Raise the `lg` column minimum from 12.5rem to 18rem
- [x] 4.2 Check 1440x800, 1280x720 and phone width: wider cards, sideways scroll inside the board only
