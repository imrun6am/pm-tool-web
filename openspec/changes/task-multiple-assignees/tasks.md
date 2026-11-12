## 1. Data

- [x] 1.1 Migration `create_task_user_table` that copies `assignee_id`, trashed tasks included
- [x] 1.2 `Task::assignees()`, `assignedTo` scope, `assigneeList()`, visibility through the pivot, `User::assignedTasks()`
- [x] 1.3 `TaskAssignees::sync()` with the lead rule, the activity entry and the notices; a `saved` hook for direct `assignee_id` writes

## 2. Writes

- [x] 2.1 `TaskRequest` and `KeepsTasksInViewerTeams`: `assignee_ids`, rules per added person, the `assignee_ids_sent` marker
- [x] 2.2 `TaskService` create, subtask create and update resolve the list (`assignee_ids` or legacy `assignee_id`)
- [x] 2.3 `AssignTaskRequest`, `BoardService::assign()` and both board controllers accept a list
- [x] 2.4 `BulkUpdateTasksRequest` keeps "assign to" as only this person

## 3. Reads

- [x] 3.1 Filters (board, task list, release tasks, API) and My tasks through `assignedTo`
- [x] 3.2 Notifications go to all assignees; the observer no longer handles assignment
- [x] 3.3 Analytics, capacity, dashboards, tasksheet picker, celebrations and onboarding through the pivot, splitting estimates
- [x] 3.4 AI assistant tools, Ask AI, the assignee suggester, score evidence and the tasksheet drafter

## 4. UI and API

- [x] 4.1 `x-multi-select` takes a `form` attribute; the task page and panel use it for assignees
- [x] 4.2 Board card shows every avatar; the picker toggles people
- [x] 4.3 Task list rows, the release task panel and subtask rows show every assignee
- [x] 4.4 `TaskResource` / `TaskSummaryResource` return `assignee_ids` and `assignees`; `docs/api-v1.md`

## 5. Verify

- [x] 5.1 `TaskMultipleAssigneesTest`: assigning, legacy fields, filters, visibility, notifications, permissions, API parity, migration
- [x] 5.2 `vendor/bin/pint --test` and `php artisan test`
- [x] 5.3 Chrome on a scratch SQLite demo: two people on a card, each one's My tasks
