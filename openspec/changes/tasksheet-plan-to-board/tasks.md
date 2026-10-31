## 1. Claude Extraction

- [x] 1.1 Add `services.anthropic.board_tasks_model` / `board_tasks_effort` config (+ `.env.example`)
- [x] 1.2 `PlanTaskExtractor` interface and `ClaudePlanTaskExtractor` (beta create, json_schema output, refusal fallback, fallback-aware text read, stop-reason checks, cap/trim), bound in `AppServiceProvider`

## 2. Board Creation

- [x] 2.1 `PlanToBoardService`: HTML→text; existing tasks (any status) sent to Claude for semantic dedupe; exact-title safety net (any status and within the batch); transactional creation (todo, assignee, creator, positions)
- [x] 2.2 `TasksheetBoardRequest` (release belongs to team and isn't completed, plan required, not full-day leave)
- [x] 2.3 `TasksheetController@addToBoard` + `PUT tasksheet/entries/board` route; save the row, run the service, flash the success, skip and error outcomes; `index` passes `boardReleases`

## 3. UI

- [x] 3.1 Drawer footer: "Board release" picker (default = release active on the day), "Add to board" button with `formaction`, sparkles icon, disabled states (no release, full-day leave) with hints, busy "Creating tasks…" state

## 4. Tests & Verification

- [x] 4.1 Feature tests with a fake extractor: creation and assignment, dedupe on repeat, other-team release rejected, empty plan and full-day leave rejected, teammate gets 403, missing key saves the row with an error, drawer renders the button and picker
- [x] 4.2 Run `php artisan test`: full suite green
- [x] 4.3 One live smoke call against the configured key with a sample plan (not real user data), then a browser check of the button flow
