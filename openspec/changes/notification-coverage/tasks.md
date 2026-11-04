## 1. Delivery

- [x] 1.1 `InboxNotification`, `NotificationLink`, scoped service binding, batching
- [x] 1.2 Move request and release status sending into `NotificationService`; route requests to someone who can decide

## 2. Triggers

- [x] 2.1 Tasks: reassigned, block cleared, sent back, due date, urgent, deleted, recurring; blocked fallback; comments silent without a user
- [x] 2.2 Group bulk edit, bulk delete, plan to board, Claude suggestions, calendar import
- [x] 2.3 Releases: created, members, dates, completed, reopened, comments
- [x] 2.4 Meeting attendees, event invite, change, cancel, tomorrow, shared notes
- [x] 2.5 Jobs: calendar import, tasksheet PDF, KPI report, Claude results
- [x] 2.6 Teams and roles

## 3. Display

- [x] 3.1 `notifications/_item` for every kind; `open` uses `NotificationLink`
- [x] 3.2 API `target` and `count`; `docs/api-v1.md`

## 4. Verify

- [x] 4.1 Feature tests: each kind, recipients, who's left out, permissions, rendering
- [x] 4.2 Full suite and Pint; `queue:restart`
