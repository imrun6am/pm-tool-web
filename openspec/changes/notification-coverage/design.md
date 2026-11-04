## Context

`NotificationService` owns recipient rules, and observers call it so that every write path behaves alike. Many new events aren't visible to a model observer because they hinge on a pivot sync (release members, attendees, recipients). Those trigger from the service that does the sync; web and API already share those services.

## Goals / Non-Goals

**Goals:** one place for recipient rules; no flood from a bulk write; no notification that reveals something the recipient can't see.

**Non-Goals:** email or chat channels, per-category muting, pruning, @mentions, reminders about releases or capacity.

## Decisions

### 1. One generic notification class for new kinds
`InboxNotification(kind, message, targetType, targetId, actor, extra)` covers every new kind. A class per kind would add 25 near-identical files. The existing classes stay, because stored rows and tests name them.

### 2. Targets, resolved when opened
The payload stores `target_type` and `target_id`, never a URL. `NotificationLink` maps them to a route and checks the viewer can still reach the record, falling back to a page with a message. Rows from before this change have no target, so it is derived from `task_id`, `triage_item_id` or `release_id`.

### 3. Batching
`NotificationService::batch(fn () => …)` buffers sends while the work runs, then sends one row per person and kind: the original row when there's one, a grouped row when there are several. It wraps the transaction, so a rollback throws and the buffer is dropped. The service is bound `scoped` so observers and services share one buffer. Used by bulk edit, bulk delete, plan to board, Claude suggestions and calendar import confirm. A kind with no grouped wording is sent row by row.

### 4. What the stored row holds
Titles only for new kinds, plus the comment excerpt on release comments (same as task comments). No note, event description, error text or score: the row outlives the recipient's access.

### 5. Who counts as an actor
Triggers take `?User $actor`; null means silent, so seeders and console stay quiet. On-purpose exceptions take no actor: recurring tasks, event reminders, and background job results. A job result goes to the requester even though they started it, because they asked and may have left the page.

### 6. Deleting a task with subtasks
Only the top task's `deleted` event notifies, covering its assignee and those of its subtasks in one row each, so deleting a parent isn't several rows for one person.

### 7. Finishing a blocked task
The model clears `blocked_at` on Done, so "block cleared" is skipped when the task finished.

## Risks / Trade-offs

- **Requests to org-level leads when a team lead can't decide:** they hear more, but such a request otherwise stalls unseen.
- **Rows are permanent:** the table has no pruning yet. Follow-up if volume grows.
- **A reassigned person who no longer sees the task isn't told,** following the visibility rule.
