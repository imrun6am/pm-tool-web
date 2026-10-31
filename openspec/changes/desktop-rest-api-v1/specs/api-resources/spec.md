## ADDED Requirements

### Requirement: Dashboard endpoint
The system SHALL provide a dashboard endpoint that returns, for full-access roles, the planning timeline for a selected year and optional quarter — the release bars with their phase segments positioned as percentages of the axis, the month columns of the axis, the headline analytics, and which releases are flagged as team conflicts — filterable by project and team and groupable by team or project. For limited-access roles it SHALL instead return the personal member dashboard.

#### Scenario: Planning timeline returned to a lead
- **WHEN** a lead requests the dashboard for a year and quarter
- **THEN** the response contains the axis months, the grouped release bars with phase segments, the analytics, and the conflict flags

#### Scenario: Conflicts flagged across the whole schedule
- **WHEN** a release overlaps a same-team release that falls outside the filtered view
- **THEN** the visible release is still flagged as conflicted

#### Scenario: Member dashboard for a developer
- **WHEN** a developer requests the dashboard
- **THEN** the personal member dashboard payload is returned

### Requirement: Project endpoints
The system SHALL expose projects as a collection and as individual records, with create, update, delete, archive, and restore. A project SHALL carry its name, description, color, archived state, and release count. Deleting SHALL be permitted only when the project has no releases; a project with releases SHALL be archived instead. Project names SHALL be unique among non-archived projects.

#### Scenario: Lead creates a project
- **WHEN** a lead posts a name and a hex color
- **THEN** the project is created and returned

#### Scenario: Duplicate active name rejected
- **WHEN** a lead creates a project whose name matches an existing active project
- **THEN** the system responds 422

#### Scenario: Archive instead of delete
- **WHEN** a lead deletes a project that has releases
- **THEN** the system refuses the delete and the project can be archived instead

### Requirement: Team endpoints
The system SHALL expose teams as a collection and as individual records, with create, update, delete, archive, restore, assignment of the team lead, and management of team membership. A team resource SHALL carry its name, description, color, archived state, its assigned lead, and its current members. Adding and removing members SHALL be restricted to roles permitted to manage team members, and removing a member SHALL record their departure rather than erase their history.

#### Scenario: Lead adds a member
- **WHEN** an authorized lead adds a user to a team
- **THEN** the user appears in the team's current members

#### Scenario: Removing a member preserves history
- **WHEN** an authorized lead removes a member
- **THEN** the member no longer appears as current, and their past tasksheet rows remain visible

#### Scenario: Team lead assignment
- **WHEN** an authorized lead assigns an active user as the team's lead
- **THEN** the team resource reports that user as its lead

### Requirement: User management endpoints
The system SHALL expose users as a collection and as individual records to roles permitted to manage users, with create, update, deactivate/reactivate, and delete. Creating a user SHALL require a name, a unique lowercase email, a role from the supported set, and a password meeting the application's password rules; updating SHALL allow the password to be omitted to keep the current one. A user resource SHALL never include the password hash or remember token.

#### Scenario: Authorized role creates a user
- **WHEN** a user-manager posts a name, email, role, and confirmed password
- **THEN** the account is created and returned without any credential fields

#### Scenario: Password optional on update
- **WHEN** a user-manager updates a user without supplying a password
- **THEN** the account is updated and the existing password is unchanged

#### Scenario: Deactivation toggles access
- **WHEN** a user-manager deactivates an account
- **THEN** the account is reported inactive and can no longer authenticate

### Requirement: Release endpoints
The system SHALL expose releases as a filterable collection and as individual records, with create, update, delete, complete, and reopen. A release SHALL carry its project, team, name, description, year, quarter, window, completion state and notes, its four ordered phases, its assigned members, and counts of its tasks, documents, comments, and off-days. The collection SHALL be filterable by status (active or completed), project, team, and year.

#### Scenario: Release created with its phases
- **WHEN** a lead creates a release supplying the four phase windows
- **THEN** the release is created with its four ordered phases and returned

#### Scenario: Phase must sit inside the release window
- **WHEN** a phase window starts before the release start or ends after the release end
- **THEN** the system responds 422 identifying the offending phase field

#### Scenario: Members must belong to the owning team
- **WHEN** a release is saved with a member who is not on the owning team
- **THEN** the system responds 422

#### Scenario: Completing a release
- **WHEN** a lead marks a release complete with optional notes
- **THEN** the release reports its completion time, the user who completed it, and the notes

### Requirement: Overlap warning returned, never blocking
The system SHALL save a release even when its window overlaps another release owned by the same team, and SHALL return the conflicting releases in the response as a warning. The system SHALL NOT reject a save because of an overlap. The system SHALL also expose the conflicts for a given release on demand.

#### Scenario: Overlapping save succeeds with a warning
- **WHEN** a lead saves a release that double-books its team
- **THEN** the save succeeds and the response carries a warning listing the conflicting releases

#### Scenario: No overlap yields no warning
- **WHEN** a release does not overlap any same-team release
- **THEN** the response carries no conflict warning

#### Scenario: Completed releases do not book the team
- **WHEN** the only overlapping release for the team is already complete
- **THEN** no conflict is reported

### Requirement: Release off-day endpoints
The system SHALL expose a release's off-days as a collection with create, bulk marking of weekends within the window, and delete. An off-day SHALL fall within its release's window and SHALL be unique per date per release. The release resource SHALL report its duration in days, its off-day count, and its resulting working-day count.

#### Scenario: Off-day outside the window rejected
- **WHEN** an off-day is created for a date outside the release window
- **THEN** the system responds 422

#### Scenario: Duplicate off-day rejected
- **WHEN** an off-day is created for a date already marked for that release
- **THEN** the system responds 422

#### Scenario: Marking weekends
- **WHEN** a lead marks weekends for a release
- **THEN** every weekend day inside the window that was not already an off-day is added

### Requirement: Release document endpoints
The system SHALL expose a release's documents as a collection with upload, authenticated download, and delete. An upload SHALL be limited to the application's allowed file types and maximum size. A document resource SHALL carry its original name, mime type, byte size, a human-readable size, its uploader, and its upload time, and SHALL NOT expose a publicly reachable storage path. Downloads SHALL stream the file only to an authenticated, authorized caller. Deleting SHALL be restricted to the leadership tier while uploading is open to contributors.

#### Scenario: Contributor uploads
- **WHEN** a non-viewer authenticated user uploads an allowed file within the size limit
- **THEN** the document is stored and its resource returned

#### Scenario: Disallowed type rejected
- **WHEN** a file of a type outside the allowed list is uploaded
- **THEN** the system responds 422 listing the allowed types

#### Scenario: Download requires authentication
- **WHEN** an unauthenticated client requests a document download
- **THEN** the system responds 401 and no file content is served

### Requirement: Task and subtask endpoints
The system SHALL expose tasks with create under a release, create of a subtask under a task, read, update, status change, and delete. A task SHALL carry its title, description, status, assignee, creator, due date, optional phase link, its release, its subtasks, and its comment count and subtask progress. The system SHALL permit only one level of nesting, SHALL default a new task's status to the initial status, and SHALL require an assignee to be a member of the release's owning team while allowing an unchanged existing assignee who has since left.

#### Scenario: Subtask of a subtask refused
- **WHEN** a client creates a subtask under a task that is already a subtask
- **THEN** the system refuses it

#### Scenario: Assignee must be on the team
- **WHEN** a task is assigned to a user who is not a member of the release's team
- **THEN** the system responds 422

#### Scenario: Unchanged assignee who left is allowed
- **WHEN** a task is updated without changing an assignee who has since left the team
- **THEN** the update succeeds

#### Scenario: Deleting a task removes its subtasks
- **WHEN** a task with subtasks is deleted
- **THEN** its subtasks and their comments are deleted with it

### Requirement: Board endpoints
The system SHALL expose the kanban board as the top-level tasks grouped into every status column, filterable by release and assignee, with every column present even when empty. The system SHALL provide a move endpoint that sets a card's status and reorders the cards in the target column in one operation.

#### Scenario: Board returns every column
- **WHEN** a client fetches the board
- **THEN** every status column is present, including those with no cards

#### Scenario: Move changes status and order together
- **WHEN** a client moves a card to another column supplying the column's new card order
- **THEN** the card's status changes and the column's card positions are renumbered

#### Scenario: Board filtered by release
- **WHEN** a client fetches the board for one release
- **THEN** only that release's cards are grouped into the columns

### Requirement: Comment endpoints
The system SHALL expose comments on both releases and tasks, listed oldest first, with create under either parent, update, and delete. A comment SHALL carry its body, its author, and its timestamps, and SHALL show a status tag for an author whose account has been deactivated or deleted.

#### Scenario: Comment on a release
- **WHEN** an authenticated user posts a comment on a release
- **THEN** the comment is created with them as author and returned

#### Scenario: Departed author is tagged
- **WHEN** a comment's author has been deleted
- **THEN** the comment still lists its author with a status tag

### Requirement: Calendar event endpoints
The system SHALL expose calendar events as a collection filterable by month or by an explicit date range, and as individual records, with create, update, and delete. An event SHALL carry its title, description, type with its color, start and optional end, all-day flag, location, optional linked release, its creator, and its attendees. An end SHALL be on or after the start. The collection SHALL report the dates each event covers so a client can lay out a month grid.

#### Scenario: Month view returned
- **WHEN** a client requests events for a month
- **THEN** every event overlapping that month is returned with the dates it covers

#### Scenario: End before start rejected
- **WHEN** an event is saved with an end earlier than its start
- **THEN** the system responds 422

#### Scenario: Any authenticated user creates an event
- **WHEN** any signed-in user creates an event
- **THEN** it is created with them as creator

### Requirement: Daily note endpoints
The system SHALL expose daily notes as a collection scoped to what the caller may see, filterable by date, with create, update, and delete. A note SHALL carry its date, its sanitized rich-text body, its visibility, its author, and — for a note shared with specific people — its recipients. The system SHALL sanitize the body on write and SHALL reject a body that has no visible content.

#### Scenario: Visually empty body rejected
- **WHEN** a note is saved with markup containing no visible text
- **THEN** the system responds 422

#### Scenario: Specific-visibility note reaches its recipients
- **WHEN** a note is shared with specific people
- **THEN** it appears for the author and those recipients and for nobody else

#### Scenario: Body is sanitized
- **WHEN** a note body contains unsafe markup
- **THEN** the stored and returned body has that markup removed

### Requirement: Meeting note endpoints
The system SHALL expose meeting notes as a collection scoped to what the caller may see, filterable by release and by general (unlinked) notes, and as individual records, with create, update, and delete. A meeting note SHALL carry its title, meeting date, sanitized body, visibility, its author, its attendees, and its optional linked release and calendar event. Only an ongoing release SHALL be linkable to a new note, while a note already linked to a release that has since completed SHALL keep that link.

#### Scenario: Completed release cannot be newly linked
- **WHEN** a meeting note is created linking a completed release
- **THEN** the system responds 422

#### Scenario: Existing link survives completion
- **WHEN** a note linked to a release is updated after that release completed
- **THEN** the update succeeds and the link is kept

#### Scenario: Attendees-only note is scoped
- **WHEN** a note's visibility is attendees-only
- **THEN** it is returned only to its attendees, its author, and leads

### Requirement: Quick link endpoints
The system SHALL expose quick links as a collection scoped to the caller, partitioned into the caller's own links and shared links, with create, update, and delete. A quick link SHALL carry its label, its URL, its visibility, its author, and its optional linked release. URLs SHALL be restricted to the http and https schemes. Limited-access roles SHALL be rejected — not silently downgraded — when they attempt to create a shared link.

#### Scenario: Non-http scheme rejected
- **WHEN** a quick link is saved with a javascript or file URL
- **THEN** the system responds 422

#### Scenario: Limited role rejected for shared visibility
- **WHEN** a developer creates a link with shared visibility
- **THEN** the system responds 422 explaining their role may only create private links

### Requirement: Tasksheet endpoints
The system SHALL expose a team's daily tasksheet for a given date — the member rows for that day with each row's plan, result, comment, tickets, work points, ticket count, ticket points, leave type, and, for leads only, the lead feedback — together with the team's recent output trend. The system SHALL provide an upsert endpoint that saves one member's row for one date, and a per-member history endpoint filterable by team and date range. Rows SHALL be shown for members whose membership covered the viewed date, including people who have since left, been deactivated, or been deleted, plus anyone with a saved entry that day. A full-day leave SHALL clear the row's task fields, while a half-day leave SHALL keep them.

#### Scenario: Row upserted rather than duplicated
- **WHEN** a member saves their row twice for the same team and date
- **THEN** the existing row is updated and no duplicate is created

#### Scenario: Departed member's history stays visible
- **WHEN** a tasksheet is viewed for a date on which a since-departed member was still on the team
- **THEN** their row is present

#### Scenario: Full-day leave clears task fields
- **WHEN** a row is saved with a casual or sick leave
- **THEN** the row's task fields are cleared

#### Scenario: Half-day leave keeps task fields
- **WHEN** a row is saved with a half-day leave and task content
- **THEN** the task content is kept

### Requirement: Performance endpoints
The system SHALL expose, to leads only, the performance team overview for a period, a per-member scorecard, the evaluation grid for a team and cadence and period, an upsert endpoint for a member's ratings, and full management of the competency catalog. Scores SHALL be integers from 1 to 5, SHALL be recorded against the period implied by the competency's cadence, SHALL be unique per team, member, competency, and period, and SHALL record their evaluator. Future periods SHALL be rejected.

#### Scenario: Grid returned for a cadence and period
- **WHEN** a lead requests the evaluation grid for their team, a cadence, and a date
- **THEN** the applicable members and competencies for that cadence are returned with any existing scores

#### Scenario: Rating upserted
- **WHEN** a lead submits ratings for a member and period twice
- **THEN** the existing scores are updated rather than duplicated

#### Scenario: Future period rejected
- **WHEN** a lead submits ratings for a period that has not begun
- **THEN** the system responds 422

#### Scenario: Empty cells skipped
- **WHEN** a lead submits a grid with some competencies left unrated
- **THEN** the rated ones are saved and the unrated ones are left without a score

### Requirement: Activity feed endpoint
The system SHALL expose the activity feed as a paginated collection, filterable by release, by subject type, by causer, and by date range, each entry carrying its event, its description, its subject, the user who caused it, its timestamp, and — for updates — the old and new values of each changed field. Performance scores SHALL never appear in the feed. The feed SHALL be restricted to full-access roles.

#### Scenario: Update entry carries the diff
- **WHEN** a record is updated and the feed is read
- **THEN** the entry lists each changed field with its old and new value

#### Scenario: Per-release history
- **WHEN** the feed is filtered to one release
- **THEN** only activity denormalized to that release is returned

#### Scenario: Limited role blocked
- **WHEN** a developer requests the activity feed
- **THEN** the system responds 403
