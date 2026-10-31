## Why

Members write their day's plan in the tasksheet ("Task plan at morning"). The same work then has to be re-typed card by card onto the kanban board, or it never reaches the board at all. One click should turn the plan into individual board tasks assigned to that member.

## What Changes

- **New "Add to board" button** beside "Save row" in the tasksheet row drawer, next to a "Board release" picker. The picker lists the team's open releases and preselects the one active on the row's date. Clicking the button:
  1. saves the row, exactly like "Save row";
  2. sends the row's plan (plus its tickets, as context) to **Claude**, which splits it into individual, actionable tasks (title plus optional description) through structured JSON output;
  3. creates each task on the board in the chosen release, in **To Do**, **assigned to the row's member**, with the clicking user as creator.
- **Duplicate protection:** tasks whose title (case-insensitive) already exists among that member's open tasks in the release are skipped, so pressing the button twice doesn't duplicate work.
- **Clear outcomes:**
  - success: "Added N tasks to the board for {member}", noting any skipped duplicates;
  - no plan, full-day leave, or a release from another team: a validation error;
  - Claude not configured or unreachable: the row is still saved, with an error explaining that no tasks were added.
- **When the button is disabled:** when the team has no open release, or a full-day leave is selected. While the request runs, a "Creating tasks…" state prevents double submits.
- **Who can use it:** anyone who can edit the row (the member for their own row, or a lead).
- **Configuration:** `services.anthropic.board_tasks_model` (default `claude-opus-5-5`) and `board_tasks_effort` (default `low`), reusing the existing `ANTHROPIC_API_KEY` and `anthropic-ai/sdk` integration.

## Capabilities

### New Capabilities
- `tasksheet-plan-to-board`: turning a tasksheet row's plan into assigned board tasks through Claude. It covers inputs, release selection, task creation rules, deduplication, permissions, errors and configuration.

### Modified Capabilities

<!-- None (no archived specs). Builds on tasksheet-entry-editor and the board. -->

## Impact

- **Backend:**
  - new `App\Services\Board\PlanTaskExtractor` interface and `ClaudePlanTaskExtractor`, bound in `AppServiceProvider`;
  - new `App\Services\Board\PlanToBoardService`;
  - `TasksheetController@addToBoard`;
  - `TasksheetBoardRequest`;
  - the route `PUT tasksheet/entries/board` (`tasksheet.entries.board`);
  - `TasksheetController@index` gains `boardReleases`.
- **Config:** two keys in `config/services.php` under `anthropic`, plus `.env.example` entries.
- **Views:** the footer of `tasksheet/_row.blade.php`, and a `sparkles` icon.
- **External:** one Claude API call per click (billed to the configured key). The plan text is sent to Anthropic.
- **Tests:** feature tests using a fake extractor (no network): task creation and assignment, dedupe, permissions, validation, the missing-key path, and the UI wiring.
