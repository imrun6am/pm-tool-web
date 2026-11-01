## Why

Nothing in PM Tool tells you when something needs you. A task can be assigned to you, someone can ask you a question on it, or it can be due tomorrow, and you only find out if you happen to look. A notification inbox gives people a reason to come back and keeps work from going stale quietly.

## What Changes

- **Database notifications** (Laravel's `notifications` table). `User` already uses `Notifiable`. Notifications are sent synchronously, so they don't depend on a running queue worker.
- **Three triggers**, each skipped for your own actions:
  - **Assigned to you:** a task is created with you as assignee, or reassigned to you.
  - **New comment:** someone comments on a task you're assigned to, created, or have commented on before.
  - **Due soon:** a daily scheduled command (`notifications:due-reminders`) tells the assignee once when a task is due tomorrow, and once on its first day overdue. There's no daily nagging.
- **Recipients are filtered by visibility:** a person is only notified about a task they may view (`Task::isVisibleTo`), so the Developer/QA team-scoping rule holds. Deactivated users are never notified.
- **Bell in the top bar:**
  - an unread badge (shows "9+" above 9);
  - a dropdown with the latest 8 and "Mark all read";
  - a link to a full **Notifications** page with All/Unread filters and pagination;
  - opening a notification marks it read and goes to the task.
  - The count refreshes on every soft navigation and every 60 seconds while the tab is visible.
- **API parity:**
  - `GET /api/v1/notifications` (paginated, `?unread=1`, with `meta.unread_count`);
  - `POST /api/v1/notifications/{id}/read`;
  - `POST /api/v1/notifications/read-all`.

## Capabilities

### New Capabilities
- `notification-inbox`: covers the notification triggers, recipient rules, the inbox UI and the API.

## Impact

- **Database:** new `notifications` table (Laravel stub).
- **Backend:**
  - `app/Notifications/{TaskAssigned,TaskCommented,TaskDueSoon}`;
  - `app/Services/NotificationService` (recipient rules, inbox queries, mark read);
  - `app/Observers/{TaskNotificationObserver,CommentNotificationObserver}`, registered in the new `EngagementServiceProvider` (no edits to `TaskService` or the comment controllers);
  - `app/Console/Commands/SendDueReminders` (scheduled daily in `routes/console.php`).
- **Web:**
  - `NotificationController`, with routes in a new `routes/engagement.php` that `routes/web.php` requires;
  - `layouts/notification-bell` (one include in `layouts/navigation`);
  - `notifications/index`;
  - `resources/js/engagement.js` (one import line in `app.js`).
- **API:** `Api\V1\NotificationController`, `NotificationResource`, `routes/api/v1/engagement.php`.
- **Docs:** `docs/api-v1.md`.
- **Tests:** triggers, self-action suppression, visibility filtering, reminder de-duplication, web pages, API, and parity.
