## ADDED Requirements

### Requirement: Team tasksheet grid
The system SHALL provide a Tasksheet section, reachable from the main navigation, showing for a selected team and date a grid with one row per team member whose role is developer or QA, with columns: Task Plan at Morning, Day End Result, Comment, Work Points, Tickets, Ticket Count, and Ticket Points. The team defaults to one of the viewer's teams and the date defaults to today, with navigation to teams and to any date — including any previous day's record.

#### Scenario: View a team's sheet for a day
- **WHEN** a user opens the Tasksheet for a team and date
- **THEN** the grid lists that team's developer and QA members as rows with their saved entry values for that date, empty cells otherwise

#### Scenario: Navigate days and teams
- **WHEN** a user switches the date or the team
- **THEN** the grid shows the selected team's rows and entries for the selected date

#### Scenario: Visit any previous day
- **WHEN** a user navigates to any past date
- **THEN** that day's saved entries are shown exactly as recorded

#### Scenario: Team with no developer or QA members
- **WHEN** a team has no developer or QA members and no saved entries for the date
- **THEN** the grid shows an empty state

#### Scenario: Former member's history is preserved
- **WHEN** a member with a saved entry for a date later leaves the team
- **THEN** that date's sheet still shows their row and entry

### Requirement: Removal freezes but preserves the member's record
When a developer or QA member is removed from a team, the system SHALL keep their rows visible on that team's sheets for all dates up to the removal — including automatic absent marks for days they did not fill — and SHALL NOT list them on dates after the removal. From the removal onward the removed member SHALL NOT be able to fill or edit that team's rows (leads still can); membership in other teams is unaffected. Re-adding the member restores their ability to fill.

#### Scenario: History up to removal stays visible
- **WHEN** a member who filled some days and missed others is removed from a team
- **THEN** that team's past sheets still show their filled rows and automatic absent marks up to the removal date, and sheets after the removal do not list them

#### Scenario: Removed member cannot fill anymore
- **WHEN** a removed member attempts to save a row for that team — new or previously saved
- **THEN** the system refuses

#### Scenario: Other team memberships unaffected
- **WHEN** a member of two teams is removed from one
- **THEN** they can still fill the other team's sheet as before

#### Scenario: Re-adding restores filling
- **WHEN** the member is added back to the team
- **THEN** they can fill that team's sheet again

### Requirement: Members fill their own row
The system SHALL allow a developer or QA team member to save their own row for a team and date (creating it on first save, updating it afterwards). The system SHALL prevent a non-lead user from saving another member's row.

#### Scenario: Member saves their row for the first time
- **WHEN** a developer or QA member saves their plan, result, comment, tickets, or points for a date
- **THEN** an entry is created for that member, team, and date with the submitted values

#### Scenario: Member updates their row
- **WHEN** the member saves the same team and date again with changed values
- **THEN** the existing entry is updated — no duplicate row is created

#### Scenario: Non-lead cannot save someone else's row
- **WHEN** a developer or QA user attempts to save a row belonging to another member
- **THEN** the system forbids the action

### Requirement: Leads may correct any row
The system SHALL allow leads (admin, CTO, team lead) to save any member's row.

#### Scenario: Lead edits a member's row
- **WHEN** a lead saves changes to a member's entry
- **THEN** the changes are stored

### Requirement: Lead-only feedback column
The system SHALL provide a per-row Feedback field that is visible to and editable by leads (admin, CTO, team lead) only. The system SHALL NOT render feedback content to developers, QA, or viewers, and SHALL ignore or reject feedback input submitted by non-leads. A member's own save SHALL NOT overwrite existing feedback.

#### Scenario: Lead writes feedback
- **WHEN** a lead saves feedback on a member's row
- **THEN** the feedback is stored and shown to leads viewing that sheet

#### Scenario: Feedback hidden from developers and QA
- **WHEN** a developer or QA user views a sheet whose rows have feedback
- **THEN** the response contains no feedback content and no Feedback column

#### Scenario: Non-lead cannot write feedback
- **WHEN** a non-lead submits a row save including a feedback value
- **THEN** the feedback value is not stored

#### Scenario: Member save preserves existing feedback
- **WHEN** a member updates their row after a lead has left feedback
- **THEN** the lead's feedback remains unchanged

### Requirement: Mark leave
The system SHALL allow a member's row to be marked with a leave type — a full-day leave (casual or sick) or a half-day leave — by the member themselves or a lead. A full-day leave SHALL display a leave badge in place of task content and SHALL clear the row's task fields. A half-day leave SHALL keep the task fields editable and count toward the row's fill status, showing a "Half day" marker alongside the tasks. Clearing the leave returns the row to a normal working row.

#### Scenario: Member marks a full-day leave
- **WHEN** a member saves their row with leave type casual or sick
- **THEN** the entry stores the leave type and the sheet shows a leave badge in place of the tasks

#### Scenario: Lead marks a member on leave
- **WHEN** a lead saves a member's row with a leave type
- **THEN** the leave is stored

#### Scenario: Full-day leave clears task fields
- **WHEN** a row containing task values is saved with a casual or sick leave
- **THEN** the stored plan, result, comment, tickets, and point values are cleared

#### Scenario: Half-day leave keeps tasks
- **WHEN** a member saves their row with half-day leave together with task values
- **THEN** the entry stores the half-day leave and retains the task values, and the sheet shows both the tasks and a "Half day" marker

#### Scenario: Invalid leave type rejected
- **WHEN** a row is saved with a leave type other than casual, sick, or half-day
- **THEN** the system rejects it with a validation error

### Requirement: Automatic absence for unfilled past days
The system SHALL display an automatic "Absent" mark on any developer or QA member's row that has no saved entry for a date that has passed. Saving the row later — with task content or a leave type — SHALL replace the automatic mark. An entry first created after its sheet date SHALL permanently display a hint that it was not added on the operating day; entries created on their sheet date SHALL show no such hint regardless of later edits.

#### Scenario: Unfilled past day shows auto-absent
- **WHEN** a user views a past date for which a developer or QA member saved no entry
- **THEN** that member's row shows an automatic "Absent" mark

#### Scenario: Backfilling replaces the auto mark but is hinted
- **WHEN** a member or lead fills in a row for a past date
- **THEN** the auto-absent mark is replaced by the saved content and the row shows a hint that it was not added on the operating day

#### Scenario: Auto mark can be changed to a leave type
- **WHEN** a member or lead sets casual or sick leave on a previously unfilled past row
- **THEN** the row shows that leave badge instead of the automatic absent mark

#### Scenario: On-time entries carry no late hint
- **WHEN** an entry created on its sheet date is edited on a later day
- **THEN** the row shows no late-fill hint

### Requirement: Per-user tasksheet history
The system SHALL provide a per-user tasksheet history view listing all of that user's rows across teams and dates, newest first, filterable by team and by a meeting-date range. It SHALL be accessible to leads and to the user themselves only; the feedback column stays lead-only. Each row SHALL link to its team's sheet for that date, and the view SHALL resolve deleted users (their history remains browsable).

#### Scenario: Lead browses a member's history with filters
- **WHEN** a lead opens a member's tasksheet history and filters by team or date range
- **THEN** only that member's matching rows are shown, newest first

#### Scenario: Member views their own history
- **WHEN** a developer or QA opens their own history
- **THEN** it loads, without the feedback column

#### Scenario: Others are forbidden
- **WHEN** a non-lead user opens another member's history
- **THEN** the system responds 403

### Requirement: Tasksheet activity logging
The system SHALL record tasksheet row saves (create, update, delete) in the activity log with the acting user and the changed fields. The system SHALL NOT include feedback content — neither values nor old→new diffs — in any activity entry.

#### Scenario: Row save is logged
- **WHEN** a user saves a tasksheet row
- **THEN** an activity entry records who saved which member's row for which date

#### Scenario: Feedback never reaches the activity log
- **WHEN** a lead saves a row including feedback
- **THEN** the recorded activity contains no feedback content

#### Scenario: Feedback-only change records nothing
- **WHEN** a lead changes only the feedback on an existing row
- **THEN** no activity entry is recorded

### Requirement: Rich text cells
The system SHALL accept rich text for plan, result, comment, tickets, and feedback, sanitizing the HTML before storing it, and SHALL store visually-empty rich-text input as empty (not counting toward a row's filled fields).

#### Scenario: Rich text sanitized
- **WHEN** a row is saved with rich text containing disallowed HTML (e.g., script tags)
- **THEN** the disallowed markup is stripped before storage

#### Scenario: Visually-empty markup counts as empty
- **WHEN** a field is submitted containing only empty markup (e.g., `<div><br></div>`)
- **THEN** it is stored as empty and does not count as a filled field

### Requirement: Entry validation
The system SHALL validate row saves: rich-text fields (plan, result, comment, tickets) are optional with sane length limits; work points, ticket count, and ticket points are optional non-negative integers; the target team, member, and date must be valid, with the member belonging to the team (or having an existing entry for it).

#### Scenario: Negative points rejected
- **WHEN** a row is saved with a negative work points value
- **THEN** the system rejects it with a validation error

#### Scenario: Save for a non-member rejected
- **WHEN** a row save targets a user who is not a member of the team and has no existing entry for it
- **THEN** the system rejects it
