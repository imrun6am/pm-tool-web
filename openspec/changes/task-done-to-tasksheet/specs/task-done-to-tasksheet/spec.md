## ADDED Requirements

### Requirement: A finished task is added to the assignee's day-end result
When a task moves to Done, the system SHALL add a line naming it to the assignee's day-end result for today, keeping what the result already holds, whoever made the move and wherever they made it.

#### Scenario: Moved to Done on the board
- **WHEN** an assigned task moves to Done
- **THEN** the assignee's result for today includes the task's title and existing text is kept

#### Scenario: Moved to Done through the API
- **WHEN** a client moves an assigned task to Done through the API
- **THEN** the same line is added

#### Scenario: No row yet
- **WHEN** the assignee has no row today
- **THEN** a row is created holding only that line

#### Scenario: Moved to Done twice
- **WHEN** a task is reopened and finished again the same day
- **THEN** its line appears once

#### Scenario: Nobody assigned
- **WHEN** a task with no assignee moves to Done
- **THEN** no tasksheet row changes

#### Scenario: On full-day leave
- **WHEN** the assignee's row today is a full-day leave
- **THEN** that row is not changed

#### Scenario: Lead feedback
- **WHEN** a line is added to a row with lead feedback
- **THEN** the feedback is unchanged

### Requirement: Reopening takes the line back out
When a task leaves Done for an open status on the day its line was added, the system SHALL remove that line if it is still as written.

#### Scenario: Reopened the same day
- **WHEN** a finished task moves back to In Review the same day
- **THEN** its line is removed and other text stays

#### Scenario: Filed away
- **WHEN** a finished task moves to Archive
- **THEN** its line stays

#### Scenario: Restored from the archive
- **WHEN** an archived task moves back to Done
- **THEN** no line is added

### Requirement: The team's sheet is chosen safely
The system SHALL use the task's team when the assignee is a member, otherwise the assignee's only team, and SHALL skip the line when neither applies.

#### Scenario: On several teams with no matching task team
- **WHEN** the assignee is on several teams and none is the task's
- **THEN** no tasksheet row changes

### Requirement: Drafts don't repeat the result
"Draft from board activity" SHALL send the row's current result so Claude doesn't repeat it, and SHALL say so when there is nothing new to add.

#### Scenario: Everything is already listed
- **WHEN** Claude finds nothing new beyond the current result
- **THEN** the member is told and the editor is unchanged
