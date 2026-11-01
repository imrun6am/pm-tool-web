## Why

Adding a task today means going to the board, picking a column and opening its "Add a card" form. When something occurs to you in the middle of a meeting note or the calendar, that's enough friction that it doesn't get written down. People keep using a task tool when capture takes seconds. The ⌘K palette also only knows about pages, so there's no quick way back to a task.

## What Changes

- **Quick capture from anywhere:**
  - Press `C` on any page (outside text fields), choose "New task" in ⌘K, or choose it in the sidebar's Create menu.
  - A small dialog asks for:
    - a **title**;
    - an optional **due date**, with None / Today / Tomorrow / Next week chips or a specific date;
    - an **assignee**, defaulting to you;
    - an optional **release**.
  - Enter saves it, and a toast confirms "Added to the board". If you're on the board or dashboard, the page refreshes in place.
  - `GET /tasks/new` is a full-page version of the same form, for direct links and as a fallback when JavaScript isn't available.
- **Who can be picked:** the same people and releases the board allows. Developers and QA see only their teammates and their own teams' releases. The team is resolved by the existing `TaskService::teamFor` rules.
- **Task search in ⌘K:** typing two or more characters also searches the tasks you're allowed to see, by title. Open tasks come first, then the most recently updated, up to 8. Results are a "Tasks" group that works with the arrow keys and Enter.
- **`?` shortcut sheet:** lists ⌘K, `C`, `?` and Esc.
- **API:** `GET /api/v1/search/tasks?q=` returns the same results. Creating tasks over the API is unchanged (`POST /api/v1/tasks`).

## Capabilities

### New Capabilities
- `quick-capture`: global task capture, task search, and the keyboard shortcut sheet.

## Impact

- **Backend:**
  - `app/Services/TaskSearchService`;
  - `app/Services/QuickCaptureService` (the pickers: assignable people and open releases per viewer; creation delegates to `TaskService::create`);
  - `QuickTaskRequest`;
  - `QuickCaptureController`, `TaskSearchController`, `Api\V1\TaskSearchController`.
- **Navigation:** `Navigation::createActions` gains "New task" (first).
- **Views:**
  - `layouts/quick-capture` (dialog plus shortcut sheet), included in `layouts/app`;
  - `tasks/quick.blade.php`;
  - `layouts/command-palette`, which gets a Tasks group.
- **JS:** `resources/js/engagement.js` (quick capture and shortcuts); `commandPalette` in `app.js` gains task results.
- **Docs and tests:** `docs/api-v1.md`, feature tests, parity.
