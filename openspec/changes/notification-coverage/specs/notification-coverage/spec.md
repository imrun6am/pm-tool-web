## ADDED Requirements

### Requirement: Recipient rules
Every notification SHALL skip the person who acted, deactivated and deleted accounts, and anyone who can't see the record. Writes with no signed-in user SHALL send nothing, except recurring tasks, event reminders and background job results.

#### Scenario: Seeding is silent
- **WHEN** a comment is written with no signed-in user
- **THEN** no notification is created

### Requirement: Sensitive data
No notification SHALL be sent for performance scores, lead feedback or standup attendance, and no stored row SHALL contain score, feedback, note or event text.

#### Scenario: Lead feedback
- **WHEN** a lead saves feedback on a member's tasksheet row
- **THEN** the member gets no notification

### Requirement: Bulk writes are grouped
A bulk write SHALL produce one row per person and kind, with the count.

#### Scenario: Bulk assign
- **WHEN** a lead assigns twelve tasks to one person in a bulk edit
- **THEN** that person has one `assigned` row saying twelve tasks, which opens their task list

#### Scenario: Template
- **WHEN** a release template creates tasks with no assignee
- **THEN** nobody is notified

### Requirement: Task events
The assignee SHALL hear when someone else reassigns the task away, clears its block, sends it back to Recheck or reopens it from Done, changes its due date, raises it to Urgent, or deletes it. A recurring rule's assignee SHALL hear when the schedule creates their task.

#### Scenario: Finishing isn't clearing a block
- **WHEN** a blocked task is moved to Done
- **THEN** no `block_cleared` row is created

### Requirement: Release events
The team lead SHALL hear when a release is created for the team. A person added to a release's members SHALL hear so. The lead and members SHALL hear when phase dates change or the release is reopened; the team's members as well when it's completed. A release comment SHALL reach the lead, members and earlier commenters.

#### Scenario: Other teams stay out
- **WHEN** a release member is a developer no longer on the release's team
- **THEN** they get no notification about it

### Requirement: Meeting, event and note events
A newly added meeting attendee, event attendee or specific-note recipient SHALL hear once. Attendees SHALL hear when an event's time or place changes or it's cancelled, and attendees and creator once the day before it starts.

#### Scenario: Private note
- **WHEN** a note stays private
- **THEN** nobody is notified, admins included

### Requirement: Background job results
Calendar imports, tasksheet PDFs, KPI reports and requested Claude results SHALL notify their requester when ready or failed, and nobody else. A scheduled standup digest SHALL notify nobody.

#### Scenario: KPI report subject
- **WHEN** a KPI report about a member finishes
- **THEN** only the requesting admin is told, with no scores in the text

### Requirement: Team and account events
A person SHALL hear when added to or removed from a team, made or no longer team lead, or when their role changes.

### Requirement: Requests reach someone who can decide
A new request SHALL go to the team's assigned lead only when their role can decide it, otherwise to org-level leads.

### Requirement: Links never dead-end
Opening a notification SHALL go to its record when the viewer can reach it, otherwise to a page with a message rather than a 403 or 404. The API SHALL expose the same `target`.

#### Scenario: Accepted request on another team
- **WHEN** a developer opens "request accepted" and the task sits on a team they aren't on
- **THEN** they land on the Requests page
