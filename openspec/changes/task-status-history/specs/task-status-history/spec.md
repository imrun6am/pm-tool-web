## ADDED Requirements

### Requirement: Status changes are recorded
The system SHALL record every status a task moves into, with the previous status, the acting user and the time, however the task is saved.

#### Scenario: Status change
- **WHEN** a user moves a task from To Do to In Review
- **THEN** a row records `todo` to `in_review`, that user and the time

#### Scenario: Creation
- **WHEN** a task is created
- **THEN** a row records its first status with no previous status

#### Scenario: Other edits
- **WHEN** a task is saved without its status changing
- **THEN** no row is added

### Requirement: Delivery figures read the status history
Completion, cycle time and rework figures SHALL be read from the status history and SHALL NOT depend on the activity log being kept.

#### Scenario: Activity log pruned
- **WHEN** a task's activity rows are removed
- **THEN** its started time, completed time and rework flag are unchanged

### Requirement: Existing history is kept
The migration SHALL copy status history from the activity log for tasks that still exist, without changing the log.

#### Scenario: Deleted task
- **WHEN** the activity log holds status moves for a task that no longer exists
- **THEN** those moves are skipped
