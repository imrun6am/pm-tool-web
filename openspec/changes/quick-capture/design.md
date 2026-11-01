## Context

The board's quick-add (`BoardController::storeTask`, `BoardService::quickAdd`) is tied to a column and a team picker. The `release-optional-tasks` change made the release optional and added `Task::visibleTo` for Developer/QA scoping. The ⌘K palette (`commandPalette` in `app.js`) filters a fixed list of server-rendered entries.

## Goals / Non-Goals

**Goals:**
- Capture in under five seconds, from any page, by keyboard alone.
- Reuse the existing task rules: `TaskService::create`, `TaskPolicy::create`, and the visibility scope.

**Non-Goals:**
- Natural-language parsing of dates or assignees ("tomorrow @sam") or Claude-powered capture.
- Searching notes, meetings or releases from ⌘K. Tasks come first.
- New board UI. The board itself is being changed by other work.

## Decisions

### 1. One endpoint, two response shapes
`POST /tasks/quick` validates with `QuickTaskRequest`.
- **Allowed values:** the assignee must be in the viewer's assignable pool, and the release must be ongoing and allowed by `TaskPolicy::create`.
- **Response to the dialog** (fetch, `Accept: application/json`): JSON `{ task: {id, title, url}, message }`.
- **Response to the full-page form:** a redirect to the task with a success flash.

### 2. Opening the dialog without touching the menus
The "New task" create action links to `GET /tasks/new`. `engagement.js` listens for `turbo:before-visit` and for clicks on links to that path, and opens the dialog instead of navigating. So the sidebar Create menu and the ⌘K entry (which calls `Turbo.visit`) both open the dialog, with no change to their rendering. With JavaScript off, the link opens the full page.

### 3. Key handling
`C` and `?` are ignored when:
- a modifier is held;
- focus is in an `input`, `textarea`, `select` or `[contenteditable]`, or in Trix;
- another dialog is open (`[aria-modal="true"]` is visible).

The dialog traps focus and restores it to the previous element on close.

### 4. Palette search
`commandPalette` keeps its page results and adds `tasks` fetched from `GET /search/tasks?q=` (150 ms debounce, out-of-order responses ignored). The active index runs over pages first, then tasks. Task rows are rendered with `x-for` in a separate "Tasks" group.

### 5. Search query
`TaskSearchService::search($viewer, $q, 8)`:
- `Task::visibleTo($viewer)`;
- `title LIKE %q%`, with `%` and `_` escaped;
- open tasks first (`CASE WHEN status IN done`), then `updated_at DESC`.

It returns models with `release` and `assignee`. The web endpoint maps them to `{id, title, status_label, release, url}`; the API uses `TaskSummaryResource`.
