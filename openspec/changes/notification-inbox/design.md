## Context

Task writes happen in many places: the board (drag and quick-add), the task page, the release tasks panel, tasksheet "add to board", and the API. Comments are posted from the web and the API. `TaskService`, the comment controllers and the board are all being edited by other changes right now (`release-optional-tasks`, team scoping). The queue worker is started by hand in development, so a queued notification may never be delivered.

## Goals / Non-Goals

**Goals:**
- One place that decides who is told about what, used by every write path.
- No new noise: never notify people about their own actions, and send due reminders at most twice per task.
- The same inbox on the web and over the API.

**Non-Goals:**
- Email, push, or a digest. A later change can add channels to the same notification classes.
- @mentions. That's a follow-up that adds a fourth trigger.
- Real-time broadcasting.
- Notifications about releases, events or notes.

## Decisions

### 1. Model observers, not service edits
Task `created`/`updated` and Comment `created` events fire on every path, web or API, without touching `TaskService`, `BoardService` or either `CommentController`. The observers only gather facts (task, actor, what changed) and call `NotificationService`, which owns the rules. The observers are registered in a new `EngagementServiceProvider`, which keeps `AppServiceProvider` untouched.

The actor is `auth()->user()`. Under `auth:sanctum`, the request's guard is the default, so API writes are attributed correctly. Writes with no authenticated user (seeders, console) send nothing.

### 2. Synchronous database channel
The notifications don't implement `ShouldQueue`. Each one is a single insert, and delivering it shouldn't depend on a worker the user runs by hand.

### 3. Payload shape
`data` holds `kind` (`assigned` | `commented` | `due_tomorrow` | `overdue`), `task_id`, `task_title`, `actor_id`, `actor_name` and `excerpt` (comment text, 140 characters, stripped of tags). The web URL is resolved when the notification is displayed (`route('tasks.show')`), so it is never stored. The API returns the same fields.

### 4. Gentle reminders
The daily command looks at open top-level and subtasks with an assignee:
- `due_date = tomorrow` gets `due_tomorrow`;
- `due_date = yesterday` gets `overdue`.

Before sending, it checks the recipient's existing notifications for the same `kind` and `task_id`, so running it twice is harmless. It runs at 08:00 app time.

### 5. Bell freshness
The bell is server-rendered in the layout, so every Turbo body swap refreshes it. A small Alpine component also polls `GET /notifications/unread-count` every 60 seconds while `document.visibilityState === 'visible'`, and clears its timer in `destroy()` so Turbo body swaps don't leak it.

## Risks / Trade-offs

- **Observers fire inside every task write.** The rules make one or two small queries per write, and only when the assignee changes or a comment is created.
- **Mass reassignment notifies each new assignee once per task.** That's acceptable at this team size.
