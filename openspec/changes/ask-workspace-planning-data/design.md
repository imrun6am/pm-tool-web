## Context

`WorkspaceAsk` retrieves records with keyword `LIKE` queries through each model's `visibleTo` scope, sends them in one structured-output call, and links the references in the answer. `AiGateway` has no tool-use loop.

## Goals / Non-Goals

**Goals:** answer the questions a team really asks from the app's own figures, with every viewer seeing exactly what their pages show them.

**Non-Goals:** conversation memory between questions, writing anything, or new analytics. Performance scores, KPI reports, tasksheet feedback and tasksheet points stay out of the context for everyone.

## Decisions

- **Retrieval stays on our side; no tool-use loop and no routing call.** Tools would mean a new gateway path and several model calls per question, and a routing call adds latency and cost to every question. Choosing the data in PHP is free, deterministic and testable with `FakeAiTransport`, and permissions stay in one visible place.
- **Risk data is always on; heavier data is asked for by wording.** Keywords miss paraphrases, so what answers "how are we doing?" (release health, blocked and overdue work) doesn't depend on them. Capacity, flow, hours, requests, notifications, tasksheets and changes load only when the wording points at them (`AskQuestion::TOPICS`). If a paraphrase misses one, the answer says the records don't cover it, rather than inventing it.
- **Access follows the pages, not a new rule.** Each digest repeats the check its page makes:

  | Data | Who | Same rule as |
  | --- | --- | --- |
  | Forecast, burn-up, status updates, off-days | Anyone who can see the release | `ReleaseController::show` |
  | Blocked, overdue, changes | Tasks the viewer can see | `Task::visibleTo` |
  | Waiting-on tasks | Only ones the viewer can see; others are named as hidden | `TaskDependencyService::loadLinks` |
  | Flow report | Leads and viewers | `full-access` route |
  | Capacity | Leads; team leads only their teams | `lead` route, `CapacityService::teamsFor` |
  | Hours report | Leads; team leads only their teams | `lead` route, `TimeReportService` |
  | Own hours | Everyone but viewers | `TaskTimeLogPolicy` |
  | Requests | The asker's own, plus what they may decide | `TriageService::queue`, `mine` |
  | Notifications | The asker's own | `NotificationService::latest` |
  | Tasksheet plan, result, leave, standup | Teams the viewer may open | `TasksheetService::teamsFor` |

- **Sensitive columns are never loaded.** Tasksheet rows select named columns, so `feedback` and points can't reach the prompt by a mistake further down.
- **Nothing here names who changed a status.** Task records already name the assignee, and the flow report is deliberately team-level. The system prompt forbids ranking or judging people from hours, matching how `TimeReportService` is meant to be used.
- **Digests cite tasks, and those tasks join the records.** A digest returns the task ids it mentions, so `[T12]` in a capacity or flow line always resolves to a link.
- **Live releases put the asker's own teams first, then in-window before overdue.** Sorting only by end date let forgotten, never-completed releases fill the ten slots.
- **Follow-up questions must name their subject.** Each question is retrieved alone, so "when will it finish?" would find nothing.
- **Team, task and release are stop words now.** "What's blocking the current release?" was matching every record containing "release".

## Risks / Trade-offs

- **Keyword topics can miss or over-match.** A miss gives a plain "not in the records" answer. An over-match costs a few more tokens. Both are cheap to tune in one list.
- **Prompts are larger.** Each section has a limit, and tasks found only for context get a shorter description. On a small scratch workspace a question took about 2,400–3,100 input tokens; real workspaces will use more, and the AI usage page shows the actual cost.
- **More queries per question.** Retrieval took 65–116 queries on that workspace, about 50 ms on local SQLite, next to a model call of several seconds.
- **Forecasts run once per live release.** That's three queries and a fast simulation each, for at most ten releases plus any named.
