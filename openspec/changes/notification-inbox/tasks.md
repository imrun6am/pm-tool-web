## 1. Data and rules

- [x] 1.1 Migration: Laravel `notifications` table
- [x] 1.2 `TaskAssigned`, `TaskCommented`, `TaskDueSoon` notifications (database channel, sync)
- [x] 1.3 `NotificationService`: recipients (self excluded, active, `isVisibleTo`), `notifyAssigned`, `notifyCommented`, `sendDueReminders`, inbox queries, mark read
- [x] 1.4 Task and comment observers, registered in `EngagementServiceProvider`
- [x] 1.5 `notifications:due-reminders` command, scheduled daily at 08:00

## 2. Web

- [x] 2.1 `routes/engagement.php` (required from `routes/web.php`): index, open, read-all, unread-count
- [x] 2.2 `NotificationController` and the `notifications/index` page with All/Unread filters
- [x] 2.3 `layouts/notification-bell` (badge, dropdown, mark all read), included in `layouts/navigation`
- [x] 2.4 Bell polling component in `resources/js/engagement.js` (Turbo-safe `destroy()`)

## 3. API

- [x] 3.1 `NotificationResource`, `Api\V1\NotificationController`, `routes/api/v1/engagement.php`
- [x] 3.2 `docs/api-v1.md`

## 4. Verification

- [x] 4.1 Feature tests: triggers, self-suppression, visibility, reminder idempotency, web pages, ownership, API and parity
- [x] 4.2 Browser check: bell, dropdown, page
