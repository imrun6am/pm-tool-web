## ADDED Requirements

### Requirement: Assignment notifications
When a task is created with an assignee, or its assignee changes, the new assignee SHALL receive an `assigned` notification. This does not apply when the new assignee is the person making the change.

#### Scenario: Lead assigns a developer
- **WHEN** a team lead assigns "Fix login bug" to a developer
- **THEN** the developer has one unread `assigned` notification naming the task and the lead

#### Scenario: Self-assignment is silent
- **WHEN** a developer assigns a task to themselves
- **THEN** no notification is created

### Requirement: Comment notifications
When someone comments on a task, these people SHALL each receive one `commented` notification with an excerpt of the comment:
- the task's assignee;
- its creator;
- every earlier commenter on that task.

The comment's author SHALL never be notified about their own comment.

#### Scenario: Conversation participants
- **WHEN** QA comments on a task that a developer is assigned to and a lead created
- **THEN** the developer and the lead each get one notification, and QA gets none

### Requirement: Recipients must be able to see the task
A notification SHALL only be created for an active user who may view the task under the task visibility rules. Developers and QA are kept to their own teams' tasks.

#### Scenario: Former team member
- **WHEN** a developer who commented on a task has since left that task's team and someone comments again
- **THEN** the developer is not notified

### Requirement: Due reminders
A daily command SHALL notify the assignee of each open task:
- once with `due_tomorrow` when it is due tomorrow;
- once with `overdue` on the first day after its due date.

Running the command again SHALL NOT create duplicates.

#### Scenario: Due tomorrow
- **WHEN** the reminder command runs and a developer's open task is due tomorrow
- **THEN** the developer receives a `due_tomorrow` notification for it

#### Scenario: Idempotent
- **WHEN** the reminder command runs twice on the same day
- **THEN** each reminder exists only once

### Requirement: Inbox on the web
Every signed-in user SHALL see a bell in the top bar:
- the bell shows their unread count;
- its dropdown lists the latest notifications and offers "Mark all read";
- it links to a Notifications page with All/Unread filters.

Opening a notification SHALL mark it read and navigate to its task. A user SHALL only ever read or change their own notifications.

#### Scenario: Open a notification
- **WHEN** a developer clicks an unread assignment notification
- **THEN** it is marked read and the task page opens

#### Scenario: Another user's notification
- **WHEN** a user requests to open a notification that belongs to someone else
- **THEN** the response is 404

### Requirement: Inbox over the API
The API SHALL expose these endpoints, scoped to the caller:
- the caller's notifications, paginated, with `meta.unread_count` and an `unread` filter;
- marking one notification read;
- marking all of them read.

#### Scenario: Desktop client lists unread notifications
- **WHEN** a client calls `GET /api/v1/notifications?unread=1`
- **THEN** it receives only the caller's unread notifications, with the same unread count the web bell shows
