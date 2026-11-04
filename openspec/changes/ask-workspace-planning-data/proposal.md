## Why

Ask your workspace only finds records whose text contains a word from the question, plus the asker's own tasks and the next ten live releases. Release planning, reports, requests and tasksheets have all been added since, and it knows nothing about them. It can't answer what a team asks most: what's at risk, what's blocked, who has capacity, what changed, and when a release will finish. Even "what did we decide in the last standup?", one of its own example questions, only works if the note happens to contain "decide" or "standup".

## What Changes

- Live releases carry their forecast, latest status update, blocked and overdue counts, working days left, off-days and current phase.
- Tasks carry priority, estimate, logged total, labels, phase, time in status, and whether they're blocked and why.
- Blocked, overdue and urgent work is always in the context, with totals, narrowed to a team or release the question names.
- The question's wording pulls in more when it asks: capacity, flow, logged hours, requests, the asker's notifications, tasksheet rows and leave, recent changes, and the latest meeting notes and daily notes.
- The context always states today, the working week and the next days off, so date answers follow the app's own calendar.
- A task quoted as `PM-123`, a person's name or a label now finds the matching work.
- The asker block says what this role can't see, so the answer says so instead of "no data".
- The output schema doesn't change.

## Capabilities

### New Capabilities
- `workspace-ask`: what Ask your workspace can answer from, and who sees what.

### Modified Capabilities
<!-- None: `openspec/specs/` has no base specs yet. -->

## Impact

- **Backend**: new `AskQuestion` and `WorkspaceDigests` in `App\Services\Ai`; `WorkspaceAsk` extended. Reuses `ReleaseProgressService`, `ReleaseStatusService`, `CapacityService`, `FlowReportService`, `TimeReportService`, `TriageService`, `TasksheetService`, `NotificationService` and `WorkingWeek`.
- **Frontend**: new example questions and wording on `ai/ask`.
- **API**: none.
- **Data**: none.
- **Cost**: larger prompts per question, bounded by per-section limits.
