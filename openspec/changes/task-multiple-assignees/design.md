## Context

`tasks.assignee_id` is read in about 90 files: filters, visibility, notifications, analytics, AI tools, the API and views. Most of those readers ask one of three questions: "whose plate is this on?" (filters, visibility, notifications, per-person analytics), "what one name do I show?" (compact lists, sorting, grouping), and "who gets credit?" (reports).

## Goals / Non-goals

**Goals:** several assignees on any task; every assignee sees the task as theirs; no notification noise; old API clients keep working; existing data moves over unchanged.

**Non-goals:** roles inside a task (reviewer, owner); per-assignee status or per-assignee estimates; showing one card per person on the board's "By person" grouping.

## Decisions

### 1. A pivot, and `assignee_id` kept as the lead
`task_user (id, task_id, user_id, created_at, updated_at)`, unique on `(task_id, user_id)`, index on `user_id`, both foreign keys cascade on delete. The auto-increment `id` records the order people were added in, which decides the next lead. Users are soft-deleted, so the cascade only affects hard deletes.

`assignee_id` is kept, not dropped. It is the **lead assignee**: the first person chosen. It stays the lead while they stay assigned. When the lead is removed, the next remaining assignee (in the order they were added) becomes lead. Invariant: `assignee_id IS NULL` exactly when the task has no rows in `task_user`, and otherwise `assignee_id` is one of them.

*Why keep it:* the readers that need one name (sorting, "By person" grouping, compact rows, the API's `assignee`) keep working unchanged, older API clients still get a meaningful `assignee_id`, and `assignee_id IS NULL` remains a cheap "unassigned" test. Dropping it would mean rewriting every reader at once for no user-visible gain.

*Alternative considered:* dropping `assignee_id`. That's cleaner, but it breaks API compatibility and turns every single-name reader into a subquery.

### 2. One write path: `TaskAssignees::sync()`
`App\Services\TaskAssignees::sync(Task $task, array $userIds, ?int $leadId = null, ?User $actor = null)` is the only code that changes the pivot. It:
1. normalises the list (unique ints, order kept, at most `Task::MAX_ASSIGNEES` = 10; requests reject more with a 422);
2. works out the lead (`$leadId` when it's in the list, else the current lead when still in the list, else the first);
3. attaches and detaches the difference, and saves `assignee_id` quietly with a guard flag, so the model hook below doesn't run again and the change is logged once;
4. records one activity entry (`assignees`: old names → new names) when the set changed, except where the model's own `assignee_id` entry already says it all (a new task, or a direct single-assignee write on a task that had at most one person);
5. notifies each added person (TaskAssigned) and each removed person ("took you off"), never the actor.

**Legacy writes still work.** Many places set `assignee_id` directly (quick add, the tasksheet's Add to board, triage, recurring tasks, meeting actions, factories). `created` and `updated` hooks on `Task` notices a change to `assignee_id` that didn't come from `sync()` and calls `sync()` with just that person, or with nobody. So a direct single-assignee write means "assign only this person", which is what it meant before. The observer's own assignee branch is removed, so notices are sent from one place.

**Bulk edit** ("assign to X") makes X each task's only assignee, even on a task X already leads with others.

**The AI assistant's `assign_task` tool** makes the person the only assignee by default, or adds them to the current assignees with `add: true`. `null` takes everyone off. The confirm step re-checks the stored list.

### 3. Request rules (web and API)
- `assignee_ids` (array of user ids) is the full list. An empty array clears it. The web form also sends an `assignee_ids_sent` marker so that ticking nobody clears the list, as `label_ids_sent` does for labels.
- `assignee_id` on its own, for older clients: `null` clears all assignees; the current lead's id changes nothing; any other id makes that person the only assignee.
- Both sent: `assignee_ids` wins, and `assignee_id` picks the lead when it's in the list.
- Each **newly added** id goes through today's rules: it must exist; on a release task it must be on the release's team; a Developer or QA may add only themselves or a current teammate; the board and bulk paths also require an active account. People already on the task always pass, so a deactivated or moved person can stay.

**Developers and QA can't add people from other teams.** That's today's rule for a single assignee, applied to each added person. A task from another team that has them as an assignee can still keep its other assignees.

### 4. Reading
- `Task::scopeAssignedTo($query, User|int $user)` is a `whereExists` on `task_user`, used by every "whose plate" query: filters, My tasks, `visibleTo`, analytics, AI tools.
- `TaskService::whereIdOrNone()` for `assignee_id`: an id becomes `assignedTo`, and `none` becomes `assignee_id IS NULL` (the same thing, by the invariant).
- `Task::assigneeList()` returns the lead first, then the others by name, from the eager-loaded `assignees` relation. Views and resources use it.

### 5. Notifications
Who gets told, per notice:
- **Assigned:** only people newly added, once.
- **Taken off:** only people removed. When nobody was added in the same change, they hear "took you off". When people were added (a replacement), the wording names them ("reassigned … to Ben"), or says "took over" when the actor put themselves on.
- **Comment, blocked, block cleared, sent back, due changed, marked urgent, deleted, due reminders, dependency finished, recurring task created:** every assignee, minus the actor.

`TaskService::update()` saves the task's other fields first and then syncs the assignees. So a person added in the same save that moves the due date gets "assigned" and not "due date changed" as well, and existing assignees get only the due-date notice.

### 6. Counting
- **Task counts per person** (open, overdue, done, throughput, workload, KPI): each assignee is credited with the whole task. Two people pairing on one task both finished it.
- **Estimated hours per person** (capacity, per-person estimated hours): the estimate is split evenly between assignees, so the sum over a team equals the task's estimate.
- **Logged time** is already per person (`task_time_logs.user_id`) and doesn't change.
- **Team, release and workspace totals** count each task once. They never sum per-person figures.

## Risks / Trade-offs

- **The "By person" board grouping shows a card only under its lead.** A card can sit in only one place for drag and drop. Each card shows every assignee's avatar, and My tasks finds it for everyone.
- **Old API clients that send `assignee_id` replace the whole list.** That matches what that field always meant. It's documented, and clients that know about co-assignees send `assignee_ids`.
- **Migration on live MySQL.** It's one `INSERT … SELECT` from `tasks` where `assignee_id` is set, and it's idempotent (`insertOrIgnore`). Back up first and run only this migration with `--path`.

## Migration Plan

1. Back up `tasks` (or the database), and note `select count(*) from tasks where assignee_id is not null`.
2. `php artisan migrate --path=database/migrations/2026_11_12_100001_create_task_user_table.php`
3. Check: that count (trashed tasks included) equals `select count(*) from task_user`.
4. Rollback: `migrate:rollback --path=…` drops the pivot. `assignee_id` still holds each task's lead, so single-assignee behaviour returns.
