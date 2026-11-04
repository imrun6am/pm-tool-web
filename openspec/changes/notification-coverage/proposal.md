## Why

The inbox only speaks about tasks, requests and release status. People miss being added to a release, invited to an event, sent work back, or that their background export finished. An audit of every feature (agreed with the user on 2026-11-04) also found faults in what exists: bulk edits send a row per task, comments notify during seeding, requests can reach someone who can't decide them, and some links end in a 403.

## What Changes

- **Tasks:** reassigned away, block cleared, sent back (Recheck or reopened), due date changed, raised to Urgent, deleted by an admin, and created by a recurring rule.
- **Releases:** created for a team, added as a member, phase dates changed, completed or reopened, and comments on a release.
- **Meetings, events, notes:** added as a meeting attendee, invited to an event, event time or place changed, cancelled, starts tomorrow, calendar import finished, daily note shared with specific people.
- **Background jobs:** tasksheet PDF, KPI report and requested Claude results, ready or failed, to the requester only.
- **Teams and accounts:** added to or removed from a team, made or no longer team lead, role changed.
- **Fixes:** grouped rows for bulk writes; comments silent without a signed-in user; requests go to someone who can decide them; a notification whose record can't be opened lands on a page with a message; blocked tasks with no team lead reach org-level leads; every send goes through `NotificationService`; the API exposes a `target`.

**Never notified:** performance scores and their notes, lead feedback on tasksheet rows, standup attendance, and anything private to someone else. In-app only: email, Mattermost and @mentions stay in Phase 4.

## Capabilities

### New Capabilities
- `notification-coverage`: which events notify whom, grouping, and where each notification leads.

### Modified Capabilities

<!-- None (no archived specs). `notification-inbox` stays as written; this builds on it. -->

## Impact

- **Backend:** `InboxNotification`, `NotificationLink`, `NotificationService` (scoped binding, batching, new triggers), new observers for teams and events, triggers in the release, event, meeting note, note, team, user, recurring, export, import and insight services and jobs.
- **Web and API:** `NotificationController::open`, `notifications/_item`, `NotificationResource`, `docs/api-v1.md`. No new routes or migrations.
- **Queue:** jobs send notifications, so a running worker needs `php artisan queue:restart`.
