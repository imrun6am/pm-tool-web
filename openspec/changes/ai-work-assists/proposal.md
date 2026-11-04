## Why

Three everyday jobs still start from a blank page: choosing who should take an unassigned card, writing the tasksheet plan and result, and writing up what happened when a release closes. The data for all three is already in the app.

## What Changes

- **Suggest an assignee:** Claude recommends up to three people for a task from those the viewer may already pick, using similar past work, open load and upcoming leave. Accepting goes through the existing assign endpoint.
- **Tasksheet drafts:** Claude drafts the morning plan from open tasks and the previous day, and the day-end result from that day's board activity. Plan items with no evidence are listed for the member to confirm, not written in as done.
- **Release retrospective:** Claude drafts closing notes in the Mark complete form. The delivery numbers are calculated by the app, and Claude writes the narrative.
- Each is a new feature key that admins can switch off and configure at `/ai/usage`. Nothing is saved until a person saves it.

## Capabilities

### New Capabilities
- `ai-work-assists`: assignee suggestions, tasksheet drafts and release retrospectives.

### Modified Capabilities
<!-- None: `openspec/specs/` has no base specs yet. -->

## Impact

- **Backend**: `config/ai.php`, `routes/ai.php`, `AiAssistController`, and new `AssigneeSuggester`, `TasksheetDrafter` and `ReleaseRetrospective` services.
- **Frontend**: `resources/js/ai.js`, new partials under `resources/views/ai/partials/`, one-line includes in the tasksheet row, the release page's complete form, the task panel and the board picker.
- **Dependencies**: uses `TaskService::cardAssignees()` and the board assign endpoint from `board-card-assignee`.
- **API**: none. Claude routes are web-only, like the other AI features.
