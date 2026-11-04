## ADDED Requirements

### Requirement: People log their own time on tasks
A user who can see a task, other than a viewer, SHALL be able to log time on it for themselves, with a date and an optional note, on the web and through the API.

#### Scenario: Log time
- **WHEN** a member enters "1h 30m" for today on a task they can see
- **THEN** a 90-minute entry is saved under their own name

#### Scenario: Another user's id
- **WHEN** a request names another user
- **THEN** the entry is still saved under the caller

#### Scenario: Task out of reach
- **WHEN** a developer logs time on another team's task
- **THEN** the system answers 403

### Requirement: Totals include subtasks
A task's total SHALL be the sum of its own entries and its subtasks' entries, and entries SHALL show which subtask they belong to.

#### Scenario: Parent total
- **WHEN** a task has 60 minutes and its subtask 30
- **THEN** the task's total is 90 minutes and the subtask's total is 30

### Requirement: Entries can be changed within limits
The author SHALL be able to change or delete an entry for 7 days. An org-level lead, or the lead of the task's team, SHALL be able to change or delete any entry at any time. Others SHALL get a 403.

#### Scenario: After a week
- **WHEN** an author edits an entry created more than 7 days ago
- **THEN** the system answers 403

#### Scenario: Lead correction
- **WHEN** the team's lead edits an old entry
- **THEN** the entry changes and keeps its original owner

### Requirement: Bad entries are refused
The system SHALL refuse a time it can't read, zero, more than 24 hours, a future date, a date older than 7 days unless the caller can correct the task's time, and anything that takes one person's day past 24 hours.

#### Scenario: Day total
- **WHEN** a member with 23 hours logged today logs 2 more
- **THEN** the system answers 422 and saves nothing

### Requirement: Viewers see totals only
A viewer SHALL see a task's total but no entries, and SHALL NOT be able to log time.

#### Scenario: Viewer reads
- **WHEN** a viewer reads a task's time on the web or the API
- **THEN** they get the totals and no entries
