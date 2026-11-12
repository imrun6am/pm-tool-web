## Why

Work is often shared: a developer and a QA tester pair on one fix, or two developers split a feature. Today a task has exactly one assignee (`tasks.assignee_id`), so the second person's work doesn't show up in their My tasks, their workload or their reports, and teams create duplicate tasks to work around it.

## What Changes

- A task, top-level or subtask, can have several assignees. A new `task_user` pivot holds all of them.
- `tasks.assignee_id` stays as the **lead assignee**. It is always one of the assignees, and it is null only when there are none. Lists that show one name, the "By person" board grouping and sorting by assignee use the lead.
- A migration copies every existing `assignee_id` into the pivot, so nothing changes for existing tasks.
- **Web:** the task page, the task panel and the board card get a multi-select assignee picker. Cards and lists show every assignee's avatar.
- **API:** tasks accept and return `assignee_ids` next to `assignee_id` and `assignee`. Sending only `assignee_id` still works (see design). `PATCH board/tasks/{task}/assignee` accepts either one.
- **Filters:** `assignee_id=X`, My tasks and the Developer/QA default match a task when X is any of its assignees. `assignee_id=none` means no assignees.
- **Visibility:** Developers and QA see a task when they are any of its assignees, as they see a task assigned to them today.
- **Notifications:** each newly added assignee is told once. Someone who is removed hears that they were taken off. Re-saving or reordering notifies nobody. Every other task notice (comments, blocks, due dates, reminders, send-backs, deletes) goes to all assignees.
- **Analytics:** each assignee is credited with the whole task in their own counts. Estimated hours are split evenly between assignees in per-person hours, so team totals count each task once.

## Capabilities

### New Capabilities
- `task-multiple-assignees`: several assignees per task, on the web and through the API, with filters, visibility, notifications and analytics that count every assignee.

### Modified Capabilities
<!-- None: `openspec/specs/` has no base specs for tasks yet. This changes behaviour described in `board-card-assignee` and `my-tasks-default`, as noted in the spec. -->

## Impact

- **Data:** new `task_user` table (task_id, user_id, timestamps). The migration copies `assignee_id`. `down()` drops the table, and `assignee_id` still holds the lead.
- **Backend:** `Task` (relation, `assignedTo` scope, visibility), a new `TaskAssignees` service (the one place that changes assignees and sends the notices), `TaskService`, `BoardService`, `TaskRequest`, `AssignTaskRequest`, `KeepsTasksInViewerTeams`, `BulkUpdateTasksRequest`, `NotificationService`, `TaskEngagementObserver`.
- **Readers:** `PeopleAnalyticsService`, `PerformanceAnalytics`, `DeliveryAnalyticsService`, `MemberAnalyticsService`, `DashboardService`, `CapacityService`, `TeamService`, `TasksheetService`, `CelebrationService`, `OnboardingService`, the AI assistant tools, `WorkspaceAsk`, `AssigneeSuggester`, `ScoreEvidence` and `TasksheetDrafter`.
- **Views:** task page, task panel, board card, task list rows, `x-multi-select` (a `form` attribute).
- **API:** `TaskResource`, `TaskSummaryResource`, the task and board endpoints, and `docs/api-v1.md`. The change is additive.
- **Tests:** a new `TaskMultipleAssigneesTest`, plus parity cases.
